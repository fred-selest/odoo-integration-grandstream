# -*- coding: utf-8 -*-

from odoo import models, fields, api, _
import logging

_logger = logging.getLogger(__name__)


class GrandstreamActiveCall(models.Model):
    _name = 'grandstream.active.call'
    _description = 'Active Call from Grandstream UCM'
    _rec_name = 'phone_number'
    _order = 'event_time desc'

    config_id = fields.Many2one(
        'grandstream.config',
        string='UCM Configuration',
        required=True,
        ondelete='cascade'
    )
    channel = fields.Char(
        string='Channel',
        required=True,
        index=True,
        help='Asterisk channel identifier'
    )
    phone_number = fields.Char(
        string='Phone Number',
        required=True,
        index=True
    )
    partner_id = fields.Many2one(
        'res.partner',
        string='Contact',
        index=True
    )
    direction = fields.Selection([
        ('inbound', 'Inbound'),
        ('outbound', 'Outbound'),
        ('internal', 'Internal')
    ], string='Direction', default='inbound')
    channel_state = fields.Char(
        string='Channel State',
        help='Current state of the channel (Ring, Up, etc.)'
    )
    event_time = fields.Datetime(
        string='Event Time',
        default=fields.Datetime.now
    )
    notified = fields.Boolean(
        string='User Notified',
        default=False,
        help='Whether users have been notified about this call'
    )
    call_ended = fields.Boolean(
        string='Call Ended',
        default=False
    )

    @api.model
    def cleanup_old_calls(self):
        """Nettoie les appels actifs de plus de 1 heure"""
        from datetime import datetime, timedelta

        cutoff_date = datetime.now() - timedelta(hours=1)
        old_calls = self.search([
            ('event_time', '<', cutoff_date)
        ])

        if old_calls:
            _logger.info(f'Cleaning up {len(old_calls)} old active calls')
            old_calls.unlink()

        return True

    def action_open_partner(self):
        """Ouvre la fiche du partenaire"""
        self.ensure_one()

        if not self.partner_id:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('No Contact'),
                    'message': _('No contact found for this phone number'),
                    'type': 'warning',
                }
            }

        return {
            'type': 'ir.actions.act_window',
            'res_model': 'res.partner',
            'res_id': self.partner_id.id,
            'view_mode': 'form',
            'target': 'current',
        }

    @api.model
    def get_active_calls_data(self):
        """Retourne les données des appels actifs pour le frontend"""
        active_calls = self.search([
            ('call_ended', '=', False)
        ], limit=50)

        return [{
            'id': call.id,
            'phone_number': call.phone_number,
            'partner_id': call.partner_id.id if call.partner_id else False,
            'partner_name': call.partner_id.name if call.partner_id else False,
            'direction': call.direction,
            'channel_state': call.channel_state,
            'event_time': call.event_time.isoformat() if call.event_time else False,
        } for call in active_calls]
