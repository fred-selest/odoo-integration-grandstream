# -*- coding: utf-8 -*-

from odoo import models, fields, api, _
from odoo.exceptions import UserError, ValidationError
import requests
import logging

_logger = logging.getLogger(__name__)


class GrandstreamConfig(models.Model):
    _name = 'grandstream.config'
    _description = 'Grandstream UCM Configuration'
    _rec_name = 'ucm_name'

    ucm_name = fields.Char(
        string='UCM Name',
        required=True,
        help='Friendly name for this UCM server'
    )
    ucm_host = fields.Char(
        string='UCM Host/IP',
        required=True,
        help='IP address or hostname of the Grandstream UCM'
    )
    ucm_port = fields.Integer(
        string='UCM Port',
        default=8089,
        required=True,
        help='API port (default: 8089)'
    )
    use_https = fields.Boolean(
        string='Use HTTPS',
        default=True,
        help='Use HTTPS for secure connection'
    )
    username = fields.Char(
        string='API Username',
        required=True,
        help='Username for UCM API access'
    )
    password = fields.Char(
        string='API Password',
        required=True,
        help='Password for UCM API access'
    )
    active = fields.Boolean(
        string='Active',
        default=True,
        help='Only active configurations will sync calls'
    )
    sync_interval = fields.Integer(
        string='Sync Interval (minutes)',
        default=15,
        required=True,
        help='How often to sync call logs (in minutes)'
    )
    last_sync_date = fields.Datetime(
        string='Last Sync Date',
        readonly=True,
        help='Last successful synchronization date'
    )
    auto_create_contacts = fields.Boolean(
        string='Auto-create Contacts',
        default=True,
        help='Automatically create contacts for unknown phone numbers'
    )
    default_country_id = fields.Many2one(
        'res.country',
        string='Default Country',
        help='Default country for phone number formatting'
    )
    call_recording_enabled = fields.Boolean(
        string='Download Call Recordings',
        default=True,
        help='Download and store call recordings'
    )
    days_to_sync = fields.Integer(
        string='Days to Sync',
        default=30,
        required=True,
        help='Number of days of call history to sync'
    )

    _sql_constraints = [
        ('ucm_name_unique', 'unique(ucm_name)', 'UCM name must be unique!'),
    ]

    @api.constrains('ucm_port')
    def _check_port(self):
        for record in self:
            if record.ucm_port < 1 or record.ucm_port > 65535:
                raise ValidationError(_('Port must be between 1 and 65535'))

    @api.constrains('sync_interval')
    def _check_sync_interval(self):
        for record in self:
            if record.sync_interval < 1:
                raise ValidationError(_('Sync interval must be at least 1 minute'))

    @api.constrains('days_to_sync')
    def _check_days_to_sync(self):
        for record in self:
            if record.days_to_sync < 1:
                raise ValidationError(_('Days to sync must be at least 1'))

    def get_api_url(self):
        """Generate base API URL"""
        self.ensure_one()
        protocol = 'https' if self.use_https else 'http'
        return f'{protocol}://{self.ucm_host}:{self.ucm_port}/api'

    def test_connection(self):
        """Test connection to Grandstream UCM"""
        self.ensure_one()

        try:
            url = f'{self.get_api_url()}/login'

            payload = {
                'username': self.username,
                'password': self.password
            }

            response = requests.post(
                url,
                json=payload,
                timeout=10,
                verify=False  # You may want to handle SSL verification properly
            )

            if response.status_code == 200:
                result = response.json()
                if result.get('response') == 'success':
                    return {
                        'type': 'ir.actions.client',
                        'tag': 'display_notification',
                        'params': {
                            'title': _('Connection Successful'),
                            'message': _('Successfully connected to Grandstream UCM!'),
                            'type': 'success',
                            'sticky': False,
                        }
                    }

            raise UserError(_('Connection failed: Invalid credentials or UCM not reachable'))

        except requests.exceptions.Timeout:
            raise UserError(_('Connection timeout. Please check the UCM host and port.'))
        except requests.exceptions.ConnectionError:
            raise UserError(_('Connection error. Please check the UCM host and port.'))
        except Exception as e:
            raise UserError(_('Connection failed: %s') % str(e))

    def action_sync_calls(self):
        """Manually trigger call synchronization"""
        self.ensure_one()
        if not self.active:
            raise UserError(_('This UCM configuration is not active'))

        call_log_obj = self.env['grandstream.call.log']
        call_log_obj.sync_calls(self)

        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Sync Started'),
                'message': _('Call synchronization has been initiated'),
                'type': 'info',
                'sticky': False,
            }
        }

    @api.model
    def cron_sync_calls(self):
        """Scheduled action to sync calls from all active UCM servers"""
        configs = self.search([('active', '=', True)])
        call_log_obj = self.env['grandstream.call.log']

        for config in configs:
            try:
                _logger.info(f'Starting call sync for UCM: {config.ucm_name}')
                call_log_obj.sync_calls(config)
                config.last_sync_date = fields.Datetime.now()
                _logger.info(f'Call sync completed for UCM: {config.ucm_name}')
            except Exception as e:
                _logger.error(f'Error syncing calls for UCM {config.ucm_name}: {str(e)}')
