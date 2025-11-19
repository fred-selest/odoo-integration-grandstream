# -*- coding: utf-8 -*-

from odoo import models, fields, api, _
from odoo.exceptions import UserError
import requests
import json
import os
import zipfile
import shutil
import tempfile
import logging

_logger = logging.getLogger(__name__)

# GitHub repository information
GITHUB_REPO = 'fred-selest/odoo-integration-grandstream'
GITHUB_API_URL = f'https://api.github.com/repos/{GITHUB_REPO}'
CURRENT_VERSION = '1.0.0'


class GrandstreamUpdateManager(models.TransientModel):
    _name = 'grandstream.update.manager'
    _description = 'Grandstream UCM Update Manager'

    current_version = fields.Char(
        string='Current Version',
        default=CURRENT_VERSION,
        readonly=True
    )
    latest_version = fields.Char(
        string='Latest Version',
        readonly=True
    )
    update_available = fields.Boolean(
        string='Update Available',
        readonly=True
    )
    release_notes = fields.Text(
        string='Release Notes',
        readonly=True
    )
    download_url = fields.Char(
        string='Download URL',
        readonly=True
    )
    release_date = fields.Char(
        string='Release Date',
        readonly=True
    )
    state = fields.Selection([
        ('check', 'Check for Updates'),
        ('available', 'Update Available'),
        ('current', 'Up to Date'),
        ('downloading', 'Downloading'),
        ('installing', 'Installing'),
        ('done', 'Done'),
        ('error', 'Error')
    ], default='check', string='State')
    error_message = fields.Text(
        string='Error Message',
        readonly=True
    )

    @api.model
    def default_get(self, fields_list):
        res = super().default_get(fields_list)
        res['current_version'] = CURRENT_VERSION
        return res

    def action_check_updates(self):
        """Check for available updates from GitHub releases"""
        self.ensure_one()

        try:
            # Get latest release from GitHub
            url = f'{GITHUB_API_URL}/releases/latest'
            response = requests.get(url, timeout=10)

            if response.status_code == 200:
                release_data = response.json()

                latest_version = release_data.get('tag_name', '').lstrip('v')
                release_notes = release_data.get('body', '')
                release_date = release_data.get('published_at', '')[:10]

                # Find the zip asset
                download_url = ''
                for asset in release_data.get('assets', []):
                    if asset['name'].endswith('.zip'):
                        download_url = asset['browser_download_url']
                        break

                # If no zip asset, use the source code zip
                if not download_url:
                    download_url = release_data.get('zipball_url', '')

                # Compare versions
                update_available = self._compare_versions(CURRENT_VERSION, latest_version)

                self.write({
                    'latest_version': latest_version,
                    'update_available': update_available,
                    'release_notes': release_notes,
                    'download_url': download_url,
                    'release_date': release_date,
                    'state': 'available' if update_available else 'current',
                    'error_message': False
                })

            elif response.status_code == 404:
                self.write({
                    'state': 'current',
                    'latest_version': CURRENT_VERSION,
                    'update_available': False,
                    'release_notes': _('No releases found on GitHub'),
                    'error_message': False
                })
            else:
                raise UserError(_('Failed to check for updates: HTTP %s') % response.status_code)

        except requests.exceptions.RequestException as e:
            self.write({
                'state': 'error',
                'error_message': _('Network error: %s') % str(e)
            })

        return {
            'type': 'ir.actions.act_window',
            'res_model': 'grandstream.update.manager',
            'res_id': self.id,
            'view_mode': 'form',
            'target': 'new',
        }

    def _compare_versions(self, current, latest):
        """Compare version strings"""
        try:
            current_parts = [int(x) for x in current.split('.')]
            latest_parts = [int(x) for x in latest.split('.')]

            # Pad with zeros if needed
            while len(current_parts) < 3:
                current_parts.append(0)
            while len(latest_parts) < 3:
                latest_parts.append(0)

            return latest_parts > current_parts

        except (ValueError, AttributeError):
            return False

    def action_download_update(self):
        """Download and install the update"""
        self.ensure_one()

        if not self.download_url:
            raise UserError(_('No download URL available'))

        self.write({'state': 'downloading'})

        try:
            # Download the release
            _logger.info(f'Downloading update from {self.download_url}')

            response = requests.get(self.download_url, timeout=60, stream=True)
            if response.status_code != 200:
                raise UserError(_('Failed to download update: HTTP %s') % response.status_code)

            # Save to temporary file
            with tempfile.NamedTemporaryFile(delete=False, suffix='.zip') as tmp_file:
                for chunk in response.iter_content(chunk_size=8192):
                    tmp_file.write(chunk)
                tmp_path = tmp_file.name

            self.write({'state': 'installing'})

            # Extract and install
            self._install_update(tmp_path)

            # Clean up
            os.unlink(tmp_path)

            self.write({
                'state': 'done',
                'error_message': False
            })

            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Update Installed'),
                    'message': _('The module has been updated to version %s. Please restart Odoo to apply changes.') % self.latest_version,
                    'type': 'success',
                    'sticky': True,
                }
            }

        except Exception as e:
            _logger.error(f'Update failed: {str(e)}')
            self.write({
                'state': 'error',
                'error_message': str(e)
            })
            raise UserError(_('Update failed: %s') % str(e))

    def _install_update(self, zip_path):
        """Extract and install the update"""
        # Get module path
        module_path = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        parent_path = os.path.dirname(module_path)

        # Create backup
        backup_path = f'{module_path}_backup'
        if os.path.exists(backup_path):
            shutil.rmtree(backup_path)
        shutil.copytree(module_path, backup_path)

        try:
            # Extract zip
            with tempfile.TemporaryDirectory() as tmp_dir:
                with zipfile.ZipFile(zip_path, 'r') as zip_ref:
                    zip_ref.extractall(tmp_dir)

                # Find the module directory in extracted content
                extracted_items = os.listdir(tmp_dir)
                if len(extracted_items) == 1 and os.path.isdir(os.path.join(tmp_dir, extracted_items[0])):
                    extract_root = os.path.join(tmp_dir, extracted_items[0])
                else:
                    extract_root = tmp_dir

                # Find grandstream_ucm_integration folder
                source_path = None
                for root, dirs, files in os.walk(extract_root):
                    if 'grandstream_ucm_integration' in dirs:
                        source_path = os.path.join(root, 'grandstream_ucm_integration')
                        break

                if not source_path:
                    raise UserError(_('Module not found in downloaded package'))

                # Remove old module (except __pycache__)
                for item in os.listdir(module_path):
                    item_path = os.path.join(module_path, item)
                    if item != '__pycache__':
                        if os.path.isdir(item_path):
                            shutil.rmtree(item_path)
                        else:
                            os.unlink(item_path)

                # Copy new files
                for item in os.listdir(source_path):
                    src = os.path.join(source_path, item)
                    dst = os.path.join(module_path, item)
                    if os.path.isdir(src):
                        shutil.copytree(src, dst)
                    else:
                        shutil.copy2(src, dst)

            # Remove backup on success
            shutil.rmtree(backup_path)

            _logger.info('Module updated successfully')

        except Exception as e:
            # Restore backup
            if os.path.exists(backup_path):
                shutil.rmtree(module_path)
                shutil.move(backup_path, module_path)
            raise

    @api.model
    def cron_check_updates(self):
        """Scheduled action to check for updates"""
        try:
            url = f'{GITHUB_API_URL}/releases/latest'
            response = requests.get(url, timeout=10)

            if response.status_code == 200:
                release_data = response.json()
                latest_version = release_data.get('tag_name', '').lstrip('v')

                if self._compare_versions(CURRENT_VERSION, latest_version):
                    # Send notification to admin users
                    admin_users = self.env['res.users'].search([
                        ('groups_id', 'in', self.env.ref('base.group_system').id)
                    ])

                    for user in admin_users:
                        self.env['mail.message'].create({
                            'message_type': 'notification',
                            'subject': _('Grandstream UCM Update Available'),
                            'body': _('A new version (%s) of Grandstream UCM Integration is available. '
                                     'Current version: %s. '
                                     'Go to GrandstreamUCM > Configuration > Check Updates to install.') % (
                                         latest_version, CURRENT_VERSION),
                            'partner_ids': [(4, user.partner_id.id)],
                        })

                    _logger.info(f'Update notification sent: v{latest_version} available')

        except Exception as e:
            _logger.error(f'Error checking for updates: {str(e)}')


class GrandstreamConfig(models.Model):
    _inherit = 'grandstream.config'

    @api.model
    def get_current_version(self):
        """Get current module version"""
        return CURRENT_VERSION
