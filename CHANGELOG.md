# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-01-15

### Added - Odoo Module
- Initial release of Grandstream UCM Integration for Odoo 17.0+
- Automatic synchronization of call logs from Grandstream UCM
- Call history display in contact records
- Call recording playback directly from Odoo
- Call statistics (duration, call count, last call)
- Automatic contact creation for unknown phone numbers
- Advanced filters (direction, type, period, contact)
- Multi-UCM server support
- Scheduled actions for automatic sync
- Complete French documentation

### Added - Dolibarr Module
- Initial release of Grandstream UCM Integration for Dolibarr 14.0+
- Automatic synchronization of call logs from Grandstream UCM
- Calls tab in third-party records with statistics
- Recording download and storage
- Notes and comments on each call
- Automatic third-party creation for unknown numbers
- Scheduled task (cron) for automatic sync
- Complete French translations
- Access control and permissions

### Technical
- REST API connector for Grandstream UCM
- Support for CDR (Call Detail Records)
- Recording file download and storage
- Phone number normalization
- Database schema with proper indexes

## [0.9.0] - 2024-01-10

### Added
- Beta release for testing
- Core synchronization functionality
- Basic UI implementation

---

[Unreleased]: https://github.com/fred-selest/odoo-integration-grandstream/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/fred-selest/odoo-integration-grandstream/releases/tag/v1.0.0
[0.9.0]: https://github.com/fred-selest/odoo-integration-grandstream/releases/tag/v0.9.0
