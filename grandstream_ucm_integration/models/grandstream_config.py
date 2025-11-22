# -*- coding: utf-8 -*-

from odoo import models, fields, api, _
from odoo.exceptions import UserError, ValidationError
import requests
import logging
import re
import urllib3

_logger = logging.getLogger(__name__)

# Suppress only the single warning from urllib3 needed.
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# Constants for call types and directions
CALL_DIRECTIONS = [
    ('inbound', 'Inbound'),
    ('outbound', 'Outbound'),
    ('internal', 'Internal')
]

CALL_TYPES = [
    ('answered', 'Answered'),
    ('missed', 'Missed'),
    ('voicemail', 'Voicemail'),
    ('busy', 'Busy'),
    ('failed', 'Failed')
]

# Retry configuration
MAX_RETRIES = 3
RETRY_DELAY = 2  # seconds


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
    verify_ssl = fields.Boolean(
        string='Verify SSL Certificate',
        default=False,
        help='Verify SSL certificate (disable for self-signed certificates)'
    )
    username = fields.Char(
        string='API Username',
        required=True,
        help='Username for UCM API access'
    )
    password = fields.Char(
        string='API Password',
        required=True,
        password=True,
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
    last_sync_status = fields.Selection([
        ('success', 'Success'),
        ('partial', 'Partial'),
        ('failed', 'Failed')
    ], string='Last Sync Status', readonly=True)
    last_sync_message = fields.Text(
        string='Last Sync Message',
        readonly=True
    )
    last_sync_count = fields.Integer(
        string='Last Sync Count',
        readonly=True,
        help='Number of calls synced in last sync'
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
    max_calls_per_sync = fields.Integer(
        string='Max Calls per Sync',
        default=1000,
        help='Maximum number of calls to sync per run (0 = unlimited)'
    )
    api_rate_limit = fields.Integer(
        string='API Rate Limit (req/min)',
        default=60,
        help='Maximum API requests per minute'
    )

    _sql_constraints = [
        ('ucm_name_unique', 'unique(ucm_name)', 'UCM name must be unique!'),
    ]

    @api.constrains('ucm_host')
    def _check_host(self):
        """Validate host format"""
        ip_pattern = r'^(\d{1,3}\.){3}\d{1,3}$'
        hostname_pattern = r'^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$'

        for record in self:
            host = record.ucm_host.strip()
            if not (re.match(ip_pattern, host) or re.match(hostname_pattern, host)):
                raise ValidationError(_('Invalid host format. Use IP address or hostname.'))

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

    def _make_api_request(self, endpoint, method='POST', data=None, params=None, timeout=30):
        """
        Make API request with retry logic and rate limiting.

        :param endpoint: API endpoint (without /api prefix)
        :param method: HTTP method (GET, POST)
        :param data: POST data (dict)
        :param params: GET parameters (dict)
        :param timeout: Request timeout in seconds
        :return: Response data or None on failure
        """
        self.ensure_one()
        url = f'{self.get_api_url()}/{endpoint}'

        import time
        last_error = None

        for attempt in range(MAX_RETRIES):
            try:
                if method == 'POST':
                    response = requests.post(
                        url,
                        json=data,
                        timeout=timeout,
                        verify=self.verify_ssl
                    )
                else:
                    response = requests.get(
                        url,
                        params=params,
                        timeout=timeout,
                        verify=self.verify_ssl
                    )

                if response.status_code == 200:
                    return response.json()
                elif response.status_code == 429:  # Rate limited
                    wait_time = int(response.headers.get('Retry-After', RETRY_DELAY * (attempt + 1)))
                    _logger.warning(f'Rate limited, waiting {wait_time}s')
                    time.sleep(wait_time)
                    continue
                elif response.status_code >= 500:  # Server error, retry
                    last_error = f'Server error: {response.status_code}'
                    time.sleep(RETRY_DELAY * (attempt + 1))
                    continue
                else:
                    last_error = f'HTTP {response.status_code}'
                    break

            except requests.exceptions.Timeout:
                last_error = 'Request timeout'
                time.sleep(RETRY_DELAY * (attempt + 1))
            except requests.exceptions.ConnectionError as e:
                last_error = f'Connection error: {str(e)}'
                time.sleep(RETRY_DELAY * (attempt + 1))
            except requests.exceptions.RequestException as e:
                last_error = str(e)
                break

        _logger.error(f'API request failed after {MAX_RETRIES} attempts: {last_error}')
        return None

    def test_connection(self):
        """Test connection to Grandstream UCM"""
        self.ensure_one()

        payload = {
            'username': self.username,
            'password': self.password
        }

        result = self._make_api_request('login', 'POST', data=payload, timeout=10)

        if result and result.get('response') == 'success':
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

    def action_sync_calls(self):
        """Manually trigger call synchronization"""
        self.ensure_one()
        if not self.active:
            raise UserError(_('This UCM configuration is not active'))

        call_log_obj = self.env['grandstream.call.log']
        synced_count, errors = call_log_obj.sync_calls(self)

        status = 'success' if not errors else ('partial' if synced_count > 0 else 'failed')
        message = f'Synced {synced_count} calls'
        if errors:
            message += f'. Errors: {len(errors)}'

        self.write({
            'last_sync_date': fields.Datetime.now(),
            'last_sync_status': status,
            'last_sync_message': message,
            'last_sync_count': synced_count
        })

        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Sync Completed'),
                'message': message,
                'type': 'success' if status == 'success' else 'warning',
                'sticky': False,
            }
        }

    @api.model
    def cron_sync_calls(self):
        """Scheduled action to sync calls from all active UCM servers"""
        configs = self.search([('active', '=', True)])
        call_log_obj = self.env['grandstream.call.log']
        total_synced = 0
        total_errors = 0

        for config in configs:
            try:
                _logger.info(f'Starting call sync for UCM: {config.ucm_name}')
                synced_count, errors = call_log_obj.sync_calls(config)

                status = 'success' if not errors else ('partial' if synced_count > 0 else 'failed')
                message = f'Synced {synced_count} calls'
                if errors:
                    message += f'. Errors: {len(errors)}'
                    for err in errors[:5]:  # Log first 5 errors
                        _logger.warning(f'Sync error: {err}')

                config.write({
                    'last_sync_date': fields.Datetime.now(),
                    'last_sync_status': status,
                    'last_sync_message': message,
                    'last_sync_count': synced_count
                })

                total_synced += synced_count
                total_errors += len(errors)

                _logger.info(f'Call sync completed for UCM: {config.ucm_name} - {message}')

            except Exception as e:
                _logger.error(f'Error syncing calls for UCM {config.ucm_name}: {str(e)}', exc_info=True)
                config.write({
                    'last_sync_status': 'failed',
                    'last_sync_message': str(e)
                })
                total_errors += 1

        _logger.info(f'Cron sync completed: {total_synced} calls synced, {total_errors} errors')
