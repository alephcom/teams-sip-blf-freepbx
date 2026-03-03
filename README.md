# teams-sip-blf-freepbx

**This module is under development and does not currently work.**

FreePBX module for configuring and managing the [teams-sip-blf](https://github.com/alephcom/teams-sip-blf) sync service. Syncs Microsoft Teams presence from your PBX using SIP BLF (Busy Lamp Field).

## Features

- **Extension → email mapping**: Manage which extensions map to which Microsoft 365 UPN (email) for Teams presence.
- **Prepopulate from FreePBX**: Load extensions and names from FreePBX; voicemail email is used as default when set. Display extension number, name, voicemail email, and configured email (they may differ).
- **App directory**: All runtime files (binary, `.env`, `extensions.json`) live in `/var/lib/asterisk/teams-sip-blf/`. The module creates this directory and writes config there.
- **Install detection**: Detects if the teams-sip-blf binary is installed; if not, shows instructions and can download the Linux binary from GitHub releases.
- **.env creation**: Creates/updates `.env` in the app directory with SIP, Azure, and path settings so the sync service can run from that directory.

## Requirements

- **This branch** targets FreePBX **13.0**. The `main` branch targets FreePBX 15; use `support-freepbx-14` for FreePBX 14.
- Optional: Core and Voicemail modules (for reading extensions and voicemail email)

## Installation

1. Clone this repository and copy the `teamsblf` module into your FreePBX admin modules directory:
   ```bash
   git clone https://github.com/your-org/teams-sip-blf-freepbx.git
   cp -r teams-sip-blf-freepbx/teamsblf /var/www/html/admin/modules/
   ```
   Or clone directly into modules and use the inner folder:
   ```bash
   cd /var/www/html/admin/modules
   git clone https://github.com/your-org/teams-sip-blf-freepbx.git
   cp -r teams-sip-blf-freepbx/teamsblf .
   ```
2. In FreePBX: **Module Admin** → find **Teams BLF Sync** → **Install** (or **Enable**).
3. Run `fwconsole reload`.
4. Open **Applications** → **Teams BLF Sync** to configure extensions and settings. If the menu item does not appear, ensure your user has permission for the module (e.g. grant "Teams BLF Sync" or "teamsblf" in **Admin** → **Administrators**).

## Configuration

- Use **Sync from FreePBX** to load all extensions and names; voicemail email is prepopulated when set. Edit **Configured email** (UPN for Teams) as needed.
- Fill in SIP and Azure settings in the form; **Save** writes `extensions.json` and `.env` to `/var/lib/asterisk/teams-sip-blf/`.
- If the binary is missing, use **Download app** or install manually from [teams-sip-blf releases](https://github.com/alephcom/teams-sip-blf/releases). Run the sync from the app directory: `cd /var/lib/asterisk/teams-sip-blf && ./sip-blf-sync`.

## License

MIT (or match teams-sip-blf). See [teams-sip-blf](https://github.com/alephcom/teams-sip-blf) for the sync application.
