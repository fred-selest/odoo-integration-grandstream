# -*- coding: utf-8 -*-

from odoo import models, fields, api, _
from odoo.exceptions import UserError
import requests
import json
import base64
from datetime import datetime, timedelta
import logging
import re

_logger = logging.getLogger(__name__)


class GrandstreamCallLog(models.Model):
    _name = 'grandstream.call.log'
    _description = 'Grandstream Call Log'
    _order = 'call_date desc'
    _rec_name = 'display_name'

    display_name = fields.Char(
        string='Name',
        compute='_compute_display_name',
        store=True
    )
    ucm_config_id = fields.Many2one(
        'grandstream.config',
        string='UCM Server',
        required=True,
        ondelete='cascade'
    )
    call_id = fields.Char(
        string='Call ID',
        required=True,
        index=True,
        help='Unique identifier from UCM'
    )
    call_date = fields.Datetime(
        string='Call Date',
        required=True,
        index=True
    )
    caller_number = fields.Char(
        string='Caller Number',
        index=True
    )
    caller_name = fields.Char(
        string='Caller Name'
    )
    called_number = fields.Char(
        string='Called Number',
        index=True
    )
    called_name = fields.Char(
        string='Called Name'
    )
    direction = fields.Selection([
        ('inbound', 'Inbound'),
        ('outbound', 'Outbound'),
        ('internal', 'Internal')
    ], string='Direction', required=True, index=True)

    call_type = fields.Selection([
        ('answered', 'Answered'),
        ('missed', 'Missed'),
        ('voicemail', 'Voicemail'),
        ('busy', 'Busy'),
        ('failed', 'Failed')
    ], string='Call Type', required=True)

    duration = fields.Integer(
        string='Duration (seconds)',
        help='Total call duration in seconds'
    )
    talk_duration = fields.Integer(
        string='Talk Duration (seconds)',
        help='Actual talk time in seconds'
    )
    duration_formatted = fields.Char(
        string='Duration',
        compute='_compute_duration_formatted',
        store=True
    )
    partner_id = fields.Many2one(
        'res.partner',
        string='Contact',
        index=True,
        help='Associated contact'
    )
    recording_file = fields.Binary(
        string='Recording',
        attachment=True,
        help='Call recording file'
    )
    recording_filename = fields.Char(
        string='Recording Filename'
    )
    recording_url = fields.Char(
        string='Recording URL',
        help='URL to download recording from UCM'
    )
    has_recording = fields.Boolean(
        string='Has Recording',
        compute='_compute_has_recording',
        store=True
    )
    extension = fields.Char(
        string='Extension'
    )
    trunk = fields.Char(
        string='Trunk'
    )
    disposition = fields.Char(
        string='Disposition'
    )
    notes = fields.Text(
        string='Notes'
    )

    _sql_constraints = [
        ('call_id_unique', 'unique(ucm_config_id, call_id)',
         'Call ID must be unique per UCM server!'),
    ]

    @api.depends('caller_number', 'called_number', 'direction', 'call_date')
    def _compute_display_name(self):
        for record in self:
            if record.direction == 'inbound':
                number = record.caller_number or 'Unknown'
            else:
                number = record.called_number or 'Unknown'

            date_str = fields.Datetime.to_string(record.call_date) if record.call_date else ''
            record.display_name = f'{number} - {date_str}'

    @api.depends('duration', 'talk_duration')
    def _compute_duration_formatted(self):
        for record in self:
            duration = record.talk_duration or record.duration or 0
            hours = duration // 3600
            minutes = (duration % 3600) // 60
            seconds = duration % 60

            if hours > 0:
                record.duration_formatted = f'{hours:02d}:{minutes:02d}:{seconds:02d}'
            else:
                record.duration_formatted = f'{minutes:02d}:{seconds:02d}'

    @api.depends('recording_file')
    def _compute_has_recording(self):
        for record in self:
            record.has_recording = bool(record.recording_file)

    @api.model
    def _get_api_session(self, config):
        """Establish API session with UCM"""
        url = f'{config.get_api_url()}/login'

        payload = {
            'username': config.username,
            'password': config.password
        }

        try:
            response = requests.post(
                url,
                json=payload,
                timeout=10,
                verify=False
            )

            if response.status_code == 200:
                result = response.json()
                if result.get('response') == 'success':
                    session_id = result.get('cookie')
                    return session_id

            raise UserError(_('Failed to authenticate with UCM'))

        except Exception as e:
            _logger.error(f'API session error: {str(e)}')
            raise UserError(_('Failed to connect to UCM: %s') % str(e))

    @api.model
    def sync_calls(self, config):
        """Sync call logs from Grandstream UCM"""
        _logger.info(f'Starting call sync for {config.ucm_name}')

        try:
            # Get API session
            session_id = self._get_api_session(config)

            # Calculate date range
            end_date = datetime.now()
            start_date = end_date - timedelta(days=config.days_to_sync)

            # Fetch call logs
            calls_data = self._fetch_call_logs(config, session_id, start_date, end_date)

            # Process each call
            synced_count = 0
            for call_data in calls_data:
                try:
                    self._process_call_data(config, call_data, session_id)
                    synced_count += 1
                except Exception as e:
                    _logger.error(f'Error processing call: {str(e)}')
                    continue

            _logger.info(f'Synced {synced_count} calls from {config.ucm_name}')

        except Exception as e:
            _logger.error(f'Call sync error: {str(e)}')
            raise

    @api.model
    def _fetch_call_logs(self, config, session_id, start_date, end_date):
        """Fetch call logs from UCM API"""
        url = f'{config.get_api_url()}/cdr'

        headers = {
            'Cookie': session_id
        }

        params = {
            'start_time': start_date.strftime('%Y-%m-%d %H:%M:%S'),
            'end_time': end_date.strftime('%Y-%m-%d %H:%M:%S'),
        }

        try:
            response = requests.get(
                url,
                headers=headers,
                params=params,
                timeout=30,
                verify=False
            )

            if response.status_code == 200:
                result = response.json()
                if isinstance(result, dict) and 'cdr' in result:
                    return result['cdr']
                elif isinstance(result, list):
                    return result
                return []

        except Exception as e:
            _logger.error(f'Error fetching call logs: {str(e)}')
            return []

    @api.model
    def _process_call_data(self, config, call_data, session_id):
        """Process and store a single call record"""
        # Extract call information (adjust based on actual UCM API response structure)
        call_id = call_data.get('uniqueid') or call_data.get('call_id')
        if not call_id:
            return

        # Check if call already exists
        existing_call = self.search([
            ('ucm_config_id', '=', config.id),
            ('call_id', '=', call_id)
        ], limit=1)

        if existing_call:
            return  # Skip already synced calls

        # Parse call data
        caller_number = self._normalize_phone_number(call_data.get('src') or call_data.get('caller'))
        called_number = self._normalize_phone_number(call_data.get('dst') or call_data.get('called'))

        # Determine direction
        direction = 'inbound'
        if call_data.get('direction'):
            direction = call_data['direction']
        elif call_data.get('type'):
            if call_data['type'] in ['outbound', 'out']:
                direction = 'outbound'

        # Determine call type
        disposition = call_data.get('disposition', '').lower()
        call_type = 'answered'
        if 'answer' in disposition:
            call_type = 'answered'
        elif 'busy' in disposition:
            call_type = 'busy'
        elif 'no answer' in disposition or 'missed' in disposition:
            call_type = 'missed'
        elif 'voicemail' in disposition:
            call_type = 'voicemail'
        else:
            call_type = 'failed'

        # Parse date
        call_date_str = call_data.get('calldate') or call_data.get('start_time')
        try:
            call_date = datetime.strptime(call_date_str, '%Y-%m-%d %H:%M:%S')
        except:
            call_date = datetime.now()

        # Find or create partner
        partner_id = self._find_or_create_partner(
            config,
            caller_number if direction == 'inbound' else called_number,
            call_data.get('src_name') or call_data.get('caller_name')
        )

        # Create call log
        vals = {
            'ucm_config_id': config.id,
            'call_id': call_id,
            'call_date': call_date,
            'caller_number': caller_number,
            'caller_name': call_data.get('src_name') or call_data.get('caller_name'),
            'called_number': called_number,
            'called_name': call_data.get('dst_name') or call_data.get('called_name'),
            'direction': direction,
            'call_type': call_type,
            'duration': int(call_data.get('duration', 0)),
            'talk_duration': int(call_data.get('billsec', 0)),
            'partner_id': partner_id,
            'extension': call_data.get('extension'),
            'trunk': call_data.get('trunk'),
            'disposition': call_data.get('disposition'),
        }

        call_log = self.create(vals)

        # Download recording if available
        if config.call_recording_enabled and call_data.get('recordingfile'):
            self._download_recording(call_log, config, call_data, session_id)

    @api.model
    def _normalize_phone_number(self, phone):
        """Normalize phone number"""
        if not phone:
            return ''

        # Remove non-numeric characters except +
        phone = re.sub(r'[^\d+]', '', phone)
        return phone

    @api.model
    def _find_or_create_partner(self, config, phone_number, name=None):
        """Find existing partner or create new one"""
        if not phone_number:
            return False

        # Search for existing partner by phone
        partner = self.env['res.partner'].search([
            '|', '|',
            ('phone', '=', phone_number),
            ('mobile', '=', phone_number),
            ('phone', 'ilike', phone_number.replace('+', ''))
        ], limit=1)

        if partner:
            return partner.id

        # Create new contact if auto-create is enabled
        if config.auto_create_contacts:
            vals = {
                'name': name or phone_number,
                'phone': phone_number,
                'type': 'contact',
                'comment': _('Auto-created from Grandstream call log'),
            }

            if config.default_country_id:
                vals['country_id'] = config.default_country_id.id

            partner = self.env['res.partner'].create(vals)
            _logger.info(f'Created new contact for {phone_number}')
            return partner.id

        return False

    @api.model
    def _download_recording(self, call_log, config, call_data, session_id):
        """Download call recording from UCM"""
        try:
            recording_file = call_data.get('recordingfile')
            if not recording_file:
                return

            url = f'{config.get_api_url()}/recording'

            headers = {
                'Cookie': session_id
            }

            params = {
                'file': recording_file
            }

            response = requests.get(
                url,
                headers=headers,
                params=params,
                timeout=60,
                verify=False
            )

            if response.status_code == 200:
                # Store recording as base64
                recording_data = base64.b64encode(response.content)

                call_log.write({
                    'recording_file': recording_data,
                    'recording_filename': f'{call_log.call_id}.wav',
                    'recording_url': recording_file,
                })

                _logger.info(f'Downloaded recording for call {call_log.call_id}')

        except Exception as e:
            _logger.error(f'Error downloading recording: {str(e)}')

    def action_download_recording(self):
        """Manually download recording"""
        self.ensure_one()

        if not self.recording_url:
            raise UserError(_('No recording available for this call'))

        config = self.ucm_config_id
        session_id = self._get_api_session(config)

        call_data = {'recordingfile': self.recording_url}
        self._download_recording(self, config, call_data, session_id)

        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Recording Downloaded'),
                'message': _('Call recording has been downloaded successfully'),
                'type': 'success',
                'sticky': False,
            }
        }

    def action_open_partner(self):
        """Open associated partner"""
        self.ensure_one()

        if not self.partner_id:
            raise UserError(_('No contact associated with this call'))

        return {
            'type': 'ir.actions.act_window',
            'name': _('Contact'),
            'res_model': 'res.partner',
            'res_id': self.partner_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
