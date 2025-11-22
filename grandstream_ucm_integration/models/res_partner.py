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
        """Calcule les statistiques d'appels de manière optimisée avec read_group"""
        if not self.ids:
            return

        CallLog = self.env['grandstream.call.log']

        # Statistiques de base par partenaire (une seule requête)
        base_stats = CallLog.read_group(
            domain=[('partner_id', 'in', self.ids)],
            fields=['partner_id', 'talk_duration:sum'],
            groupby=['partner_id']
        )
        base_stats_dict = {
            stat['partner_id'][0]: {
                'count': stat['partner_id_count'],
                'talk_duration': stat['talk_duration'] or 0
            }
            for stat in base_stats if stat['partner_id']
        }

        # Statistiques par direction (une seule requête)
        direction_stats = CallLog.read_group(
            domain=[('partner_id', 'in', self.ids)],
            fields=['partner_id', 'direction'],
            groupby=['partner_id', 'direction'],
            lazy=False
        )
        direction_dict = {}
        for stat in direction_stats:
            if stat['partner_id']:
                partner_id = stat['partner_id'][0]
                if partner_id not in direction_dict:
                    direction_dict[partner_id] = {}
                direction_dict[partner_id][stat['direction']] = stat['__count']

        # Statistiques par type d'appel (une seule requête)
        type_stats = CallLog.read_group(
            domain=[('partner_id', 'in', self.ids)],
            fields=['partner_id', 'call_type'],
            groupby=['partner_id', 'call_type'],
            lazy=False
        )
        type_dict = {}
        for stat in type_stats:
            if stat['partner_id']:
                partner_id = stat['partner_id'][0]
                if partner_id not in type_dict:
                    type_dict[partner_id] = {}
                type_dict[partner_id][stat['call_type']] = stat['__count']

        # Date du dernier appel (une seule requête)
        last_call_query = CallLog.read_group(
            domain=[('partner_id', 'in', self.ids)],
            fields=['partner_id', 'call_date:max'],
            groupby=['partner_id']
        )
        last_call_dict = {
            stat['partner_id'][0]: stat['call_date']
            for stat in last_call_query if stat['partner_id']
        }

        # Appliquer les statistiques à chaque partenaire
        for partner in self:
            stats = base_stats_dict.get(partner.id, {'count': 0, 'talk_duration': 0})
            directions = direction_dict.get(partner.id, {})
            types = type_dict.get(partner.id, {})

            partner.call_count = stats['count']
            partner.inbound_call_count = directions.get('inbound', 0)
            partner.outbound_call_count = directions.get('outbound', 0)
            partner.missed_call_count = types.get('missed', 0)
            partner.answered_call_count = types.get('answered', 0)

            # Temps de conversation total
            total_seconds = stats['talk_duration']
            partner.total_talk_time = total_seconds

            # Formatage du temps
            hours = total_seconds // 3600
            minutes = (total_seconds % 3600) // 60
            seconds = total_seconds % 60

            if hours > 0:
                partner.total_talk_time_formatted = f'{hours}h {minutes}m {seconds}s'
            elif minutes > 0:
                partner.total_talk_time_formatted = f'{minutes}m {seconds}s'
            else:
                partner.total_talk_time_formatted = f'{seconds}s'

            # Date du dernier appel
            partner.last_call_date = last_call_dict.get(partner.id, False)

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
