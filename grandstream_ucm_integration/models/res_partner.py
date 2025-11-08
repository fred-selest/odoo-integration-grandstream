# -*- coding: utf-8 -*-

from odoo import models, fields, api, _


class ResPartner(models.Model):
    _inherit = 'res.partner'

    call_log_ids = fields.One2many(
        'grandstream.call.log',
        'partner_id',
        string='Call Logs'
    )
    call_count = fields.Integer(
        string='Total Calls',
        compute='_compute_call_statistics',
        store=True
    )
    inbound_call_count = fields.Integer(
        string='Inbound Calls',
        compute='_compute_call_statistics',
        store=True
    )
    outbound_call_count = fields.Integer(
        string='Outbound Calls',
        compute='_compute_call_statistics',
        store=True
    )
    missed_call_count = fields.Integer(
        string='Missed Calls',
        compute='_compute_call_statistics',
        store=True
    )
    total_talk_time = fields.Integer(
        string='Total Talk Time (seconds)',
        compute='_compute_call_statistics',
        store=True
    )
    total_talk_time_formatted = fields.Char(
        string='Total Talk Time',
        compute='_compute_call_statistics',
        store=True
    )
    last_call_date = fields.Datetime(
        string='Last Call',
        compute='_compute_call_statistics',
        store=True
    )
    answered_call_count = fields.Integer(
        string='Answered Calls',
        compute='_compute_call_statistics',
        store=True
    )

    @api.depends('call_log_ids', 'call_log_ids.direction', 'call_log_ids.call_type',
                 'call_log_ids.talk_duration', 'call_log_ids.call_date')
    def _compute_call_statistics(self):
        for partner in self:
            calls = partner.call_log_ids

            partner.call_count = len(calls)
            partner.inbound_call_count = len(calls.filtered(lambda c: c.direction == 'inbound'))
            partner.outbound_call_count = len(calls.filtered(lambda c: c.direction == 'outbound'))
            partner.missed_call_count = len(calls.filtered(lambda c: c.call_type == 'missed'))
            partner.answered_call_count = len(calls.filtered(lambda c: c.call_type == 'answered'))

            # Calculate total talk time
            total_seconds = sum(calls.mapped('talk_duration'))
            partner.total_talk_time = total_seconds

            # Format talk time
            hours = total_seconds // 3600
            minutes = (total_seconds % 3600) // 60
            seconds = total_seconds % 60

            if hours > 0:
                partner.total_talk_time_formatted = f'{hours}h {minutes}m {seconds}s'
            elif minutes > 0:
                partner.total_talk_time_formatted = f'{minutes}m {seconds}s'
            else:
                partner.total_talk_time_formatted = f'{seconds}s'

            # Last call date
            if calls:
                partner.last_call_date = max(calls.mapped('call_date'))
            else:
                partner.last_call_date = False

    def action_view_call_logs(self):
        """Open call logs for this partner"""
        self.ensure_one()

        return {
            'name': _('Call Logs'),
            'type': 'ir.actions.act_window',
            'res_model': 'grandstream.call.log',
            'view_mode': 'tree,form',
            'domain': [('partner_id', '=', self.id)],
            'context': {'default_partner_id': self.id},
            'target': 'current',
        }
