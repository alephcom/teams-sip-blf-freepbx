# Changelog

All notable changes to the Teams BLF Sync FreePBX module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.0.3] - FreePBX 13 support branch

### Added

- **FreePBX 13 support branch**: This branch declares compatibility with FreePBX 13.0 (framework ge 13.0). Use the `support-freepbx-13` branch for FreePBX 13; use `support-freepbx-14` for FreePBX 14 and `main` for FreePBX 15.

## [0.0.1] - Initial release

### Added

- **Applications → Teams BLF Sync** menu and config page.
- Extension → email (UPN) mapping for the teams-sip-blf sync service; data stored in `teamsblf_extensions` and written to `/var/lib/asterisk/teams-sip-blf/extensions.json`.
- **Prepopulate from FreePBX**: Sync from FreePBX loads extensions and names from Core/users; voicemail email from `voicemail.conf` is used as default configured email when adding new rows.
- Four-column list: Extension, Name (from FreePBX), Voicemail email (from FreePBX), Configured email (editable). Add, Edit, Delete, and Sync from FreePBX actions.
- **Settings form** for .env: SIP_SERVER, SIP_TRANSPORT, SIP_USERNAME, SIP_PASSWORD, SIP_CONTACT_IP, STUN_SERVERS, AZURE_TENANT_ID, AZURE_CLIENT_ID, AZURE_CLIENT_SECRET. Save writes both `extensions.json` and `.env` to the app directory.
- **Install detection**: Banner when teams-sip-blf binary is not installed, with instructions and a **Download app** button.
- **Download app**: Fetches the latest Linux amd64 binary from GitHub releases into `/var/lib/asterisk/teams-sip-blf/sip-blf-sync` and sets execute permission.
- App directory `/var/lib/asterisk/teams-sip-blf/` created on module install; `.env` and `extensions.json` paths fixed to that directory.
- English i18n strings in `teamsblf/i18n/en_US.php`.

[0.0.3]: https://github.com/your-org/teams-sip-blf-freepbx/releases/tag/v0.0.3
[0.0.1]: https://github.com/your-org/teams-sip-blf-freepbx/releases/tag/v0.0.1
