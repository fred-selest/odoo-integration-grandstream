# -*- coding: utf-8 -*-

from odoo import models, fields, api, _
import logging
import threading
import socket
import time
from datetime import datetime

_logger = logging.getLogger(__name__)

# Dictionnaire global pour stocker les threads de monitoring
_monitor_threads = {}
_monitor_lock = threading.Lock()


class GrandstreamAMIMonitor(models.TransientModel):
    _name = 'grandstream.ami.monitor'
    _description = 'Grandstream AMI Monitor Service'

    @api.model
    def start_monitor(self, config_id):
        """Démarre le monitoring AMI pour une configuration"""
        config = self.env['grandstream.config'].sudo().browse(config_id)

        if not config.exists():
            _logger.error(f'Configuration {config_id} not found')
            return False

        with _monitor_lock:
            # Arrêter le thread existant s'il y en a un
            if config_id in _monitor_threads:
                self.stop_monitor(config_id)

            # Créer et démarrer un nouveau thread
            thread = threading.Thread(
                target=self._ami_monitor_thread,
                args=(config_id,),
                daemon=True,
                name=f'AMI-Monitor-{config_id}'
            )
            thread.start()
            _monitor_threads[config_id] = thread

            _logger.info(f'Started AMI monitor for config {config.ucm_name}')

            # Mettre à jour le statut
            config.write({
                'ami_monitor_status': 'running',
                'ami_error_message': False
            })

        return True

    @api.model
    def stop_monitor(self, config_id):
        """Arrête le monitoring AMI pour une configuration"""
        with _monitor_lock:
            if config_id in _monitor_threads:
                # Le thread va s'arrêter automatiquement via son flag daemon=True
                del _monitor_threads[config_id]
                _logger.info(f'Stopped AMI monitor for config {config_id}')

        config = self.env['grandstream.config'].sudo().browse(config_id)
        if config.exists():
            config.write({
                'ami_monitor_status': 'stopped',
                'ami_error_message': False
            })

        return True

    def _ami_monitor_thread(self, config_id):
        """Thread principal de monitoring AMI"""
        _logger.info(f'AMI monitor thread started for config {config_id}')

        with api.Environment.manage():
            with self.pool.cursor() as cr:
                env = api.Environment(cr, self.env.uid, {})
                config = env['grandstream.config'].sudo().browse(config_id)

                if not config.exists():
                    _logger.error(f'Config {config_id} not found in monitor thread')
                    return

                while config_id in _monitor_threads:
                    try:
                        self._connect_and_monitor(env, config)
                    except Exception as e:
                        _logger.error(f'AMI monitor error for {config.ucm_name}: {str(e)}', exc_info=True)

                        # Mettre à jour le statut d'erreur
                        config.write({
                            'ami_monitor_status': 'error',
                            'ami_error_message': str(e)
                        })
                        env.cr.commit()

                        # Attendre avant de réessayer
                        time.sleep(30)

                _logger.info(f'AMI monitor thread stopped for config {config_id}')

    def _connect_and_monitor(self, env, config):
        """Connexion AMI et surveillance des événements"""
        sock = None

        try:
            # Connexion au serveur AMI
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.settimeout(60)
            sock.connect((config.ucm_host, config.ami_port))

            # Lire le banner
            banner = sock.recv(1024).decode('utf-8', errors='ignore')
            if 'Asterisk Call Manager' not in banner:
                raise Exception('Invalid AMI server response')

            # Login
            login_cmd = f"Action: Login\r\nUsername: {config.ami_username}\r\nSecret: {config.ami_password}\r\n\r\n"
            sock.send(login_cmd.encode('utf-8'))

            time.sleep(0.5)
            response = sock.recv(4096).decode('utf-8', errors='ignore')

            if 'Success' not in response:
                raise Exception('AMI authentication failed')

            _logger.info(f'AMI connected successfully for {config.ucm_name}')

            # Mettre à jour le statut
            config.write({
                'ami_monitor_status': 'running',
                'ami_error_message': False
            })
            env.cr.commit()

            # Buffer pour les événements partiels
            buffer = ''

            # Boucle de lecture des événements
            while config.id in _monitor_threads:
                try:
                    data = sock.recv(4096).decode('utf-8', errors='ignore')
                    if not data:
                        _logger.warning('AMI connection closed by server')
                        break

                    buffer += data

                    # Traiter les événements complets
                    while '\r\n\r\n' in buffer:
                        event_text, buffer = buffer.split('\r\n\r\n', 1)
                        self._process_ami_event(env, config, event_text)

                except socket.timeout:
                    # Timeout normal, continuer
                    continue

        except Exception as e:
            _logger.error(f'AMI connection error: {str(e)}')
            raise

        finally:
            if sock:
                try:
                    sock.close()
                except:
                    pass

    def _process_ami_event(self, env, config, event_text):
        """Traite un événement AMI"""
        try:
            event = self._parse_ami_event(event_text)

            if not event:
                return

            event_name = event.get('Event', '')

            # Log des événements d'appels
            _logger.debug(f'AMI Event: {event_name}')

            # Traiter les événements Newchannel, Newstate, etc.
            if event_name in ['Newchannel', 'Newstate', 'DialBegin', 'DialEnd']:
                self._handle_call_event(env, config, event)

            # Mettre à jour la date du dernier événement
            config.write({
                'ami_last_event_date': fields.Datetime.now()
            })
            env.cr.commit()

        except Exception as e:
            _logger.error(f'Error processing AMI event: {str(e)}', exc_info=True)

    def _parse_ami_event(self, event_text):
        """Parse un événement AMI en dictionnaire"""
        event = {}
        for line in event_text.split('\r\n'):
            if ': ' in line:
                key, value = line.split(': ', 1)
                event[key] = value
        return event

    def _handle_call_event(self, env, config, event):
        """Gère un événement d'appel"""
        try:
            # Extraire les informations de l'événement
            caller_id = event.get('CallerIDNum', '')
            connected_line = event.get('ConnectedLineNum', '')
            channel = event.get('Channel', '')
            channel_state = event.get('ChannelStateDesc', '')
            context = event.get('Context', '')

            # Ignorer les événements internes
            if not caller_id or 'Local' in channel:
                return

            # Déterminer la direction de l'appel
            direction = 'inbound' if 'from-trunk' in context else 'outbound'
            phone_number = caller_id if direction == 'inbound' else connected_line

            # Nettoyer le numéro
            phone_number = re.sub(r'[^0-9+]', '', phone_number)

            if not phone_number or len(phone_number) < 3:
                return

            # Créer ou mettre à jour l'appel actif
            ActiveCall = env['grandstream.active.call'].sudo()

            existing_call = ActiveCall.search([
                ('channel', '=', channel),
                ('config_id', '=', config.id)
            ], limit=1)

            call_data = {
                'config_id': config.id,
                'channel': channel,
                'phone_number': phone_number,
                'direction': direction,
                'channel_state': channel_state,
                'event_time': fields.Datetime.now()
            }

            # Chercher le partenaire
            partner = self._find_partner_by_phone(env, phone_number)
            if partner:
                call_data['partner_id'] = partner.id

            if existing_call:
                existing_call.write(call_data)
            else:
                # Nouvel appel - déclencher la notification
                call = ActiveCall.create(call_data)

                # Envoyer notification seulement pour les appels entrants en état Ring
                if direction == 'inbound' and channel_state in ['Ring', 'Ringing']:
                    self._notify_incoming_call(env, call)

            env.cr.commit()

        except Exception as e:
            _logger.error(f'Error handling call event: {str(e)}', exc_info=True)

    def _find_partner_by_phone(self, env, phone_number):
        """Cherche un partenaire par numéro de téléphone"""
        Partner = env['res.partner'].sudo()

        # Nettoyer le numéro pour la recherche
        clean_number = re.sub(r'[^0-9]', '', phone_number)

        # Chercher par téléphone ou mobile
        partner = Partner.search([
            '|',
            ('phone', 'ilike', clean_number[-9:]),  # Derniers 9 chiffres
            ('mobile', 'ilike', clean_number[-9:])
        ], limit=1)

        return partner if partner else None

    def _notify_incoming_call(self, env, call):
        """Envoie une notification pour un appel entrant"""
        try:
            # Créer un message bus pour notifier tous les utilisateurs
            env['bus.bus']._sendone(
                'grandstream_incoming_call',
                'grandstream.active.call',
                {
                    'id': call.id,
                    'phone_number': call.phone_number,
                    'partner_id': call.partner_id.id if call.partner_id else False,
                    'partner_name': call.partner_id.name if call.partner_id else False,
                    'direction': call.direction,
                    'config_name': call.config_id.ucm_name
                }
            )

            _logger.info(f'Notification sent for incoming call from {call.phone_number}')

        except Exception as e:
            _logger.error(f'Error sending notification: {str(e)}', exc_info=True)
