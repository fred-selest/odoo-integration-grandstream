# -*- coding: utf-8 -*-
{
    'name': 'Grandstream UCM Integration',
    'version': '17.0.1.0.0',
    'category': 'Phone/VoIP',
    'summary': 'Integration with Grandstream UCM for call logs and recordings',
    'description': """
        Grandstream UCM Integration
        ============================

        This module provides integration with Grandstream UCM (Unified Communications Manager)
        to retrieve and display call logs and recordings in Odoo.

        Features:
        ---------
        * Automatic synchronization of call logs from Grandstream UCM
        * Display call history in contact records
        * Play call recordings directly from Odoo
        * Track call statistics (duration, number of calls)
        * Automatic contact creation for unknown callers
        * Configurable sync intervals

        Requirements:
        -------------
        * Odoo 17.0 or later
        * Grandstream UCM with API access enabled
        * Python requests library
    """,
    'author': 'Your Company',
    'website': 'https://www.yourcompany.com',
    'license': 'LGPL-3',
    'depends': [
        'base',
        'contacts',
        'phone_validation',
    ],
    'external_dependencies': {
        'python': ['requests'],
    },
    'data': [
        'security/ir.model.access.csv',
        'data/ir_cron.xml',
        'views/grandstream_config_views.xml',
        'views/call_log_views.xml',
        'views/res_partner_views.xml',
        'views/menu_views.xml',
    ],
    'images': ['static/description/icon.png'],
    'installable': True,
    'application': True,
    'auto_install': False,
}
