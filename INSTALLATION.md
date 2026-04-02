# Quick Start: Yuna WordPress Helper

## A) Download and install
- Download this repository ZIP from GitHub.
- In WordPress: `Plugins` → `Add New Plugin` → `Upload Plugin`.
- Upload ZIP, install, and activate **Yuna WordPress Helper**.

## B) Connect to your public GitHub
- Go to **Yuna Helper** in WP Admin.
- Set **GitHub Owner** (username or organization).
- Save and click **Refresh Catalog**.

## C) Make your plugin repos visible
For every plugin repo you want to manage:
1. Create at least one GitHub Release.
2. Use release tag matching plugin version (`v1.0.0` etc.).
3. Add release notes (used as changelog view in helper).
4. Add ZIP release asset for plugin install/update package.
5. In plugin main file, set:
   - `Version: x.y.z`
   - `Update URI: https://github.com/OWNER/REPO`

## D) Manage updates
- In Yuna Helper table:
  - Compare Installed vs Latest.
  - Read release log.
  - Click **Download ZIP** to install/update plugin package.

## E) Public-repo mode security basics
- Access restricted to WP admins (`manage_options`).
- Settings input sanitized.
- No GitHub token stored (public-only mode).
- Data cached for 10 minutes.
