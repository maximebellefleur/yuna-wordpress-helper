# Yuna WordPress Helper

Yuna WordPress Helper is a WordPress admin plugin that turns each client site into a central **GitHub plugin hub**.

It lets you:

- set a public GitHub owner (user or org),
- read all public repositories with a release,
- compare installed plugin version vs latest GitHub release,
- read release notes/changelog from GitHub,
- open/download the latest ZIP package.

---

## 1) Install this plugin on a client WordPress site

1. Download this repository as a ZIP from GitHub (`Code` → `Download ZIP`).
2. In WordPress Admin, go to `Plugins` → `Add New Plugin` → `Upload Plugin`.
3. Upload the ZIP and click `Install Now`.
4. Click `Activate`.

> Alternative: clone/copy this folder into `wp-content/plugins/yuna-wordpress-helper` and activate from `Plugins`.

---

## 2) Connect the plugin to GitHub

1. In WordPress Admin, open **Yuna Helper** (left sidebar).
2. In **GitHub Owner**, enter your public GitHub username or organization.
3. Click **Save Settings**.
4. Click **Refresh Catalog**.

The table will populate with repositories that have at least one GitHub release.

---

## 3) Prepare each managed plugin repo (important)

For each plugin repository you want to manage:

1. Add/update the plugin header in the plugin main file:

```php
/**
 * Plugin Name: My Plugin
 * Version: 1.2.0
 * Update URI: https://github.com/YOUR_OWNER/YOUR_REPO
 */
```

2. Create a GitHub release (tag like `v1.2.0` or `1.2.0`).
3. Attach a **ZIP release asset** that contains the final WordPress plugin folder structure.
   - Example ZIP root: `my-plugin/my-plugin.php`

Why this matters:
- `Update URI` helps Yuna Helper match an installed plugin to its GitHub repo.
- Release tag is used as the latest version.
- Release body is shown as changelog.

---

## 4) How update logistics work

Inside **Yuna Helper** catalog:

- **Installed**: version from currently installed plugin (if `Update URI` matches repo URL).
- **Latest**: version from the latest release tag on GitHub.
- **Update available**: shown when installed version is lower than latest.
- **Release Log**: reads latest release notes from GitHub.
- **Download ZIP**: opens latest release ZIP package for manual install/update.

Recommended update workflow:
1. Build plugin updates in your plugin repo.
2. Bump plugin header version.
3. Create release + notes + ZIP asset.
4. On client site, open Yuna Helper, review version diff/changelog, download/update.

---

## 5) Security & operational notes

- This setup uses **public repositories only** (no token required).
- Only users with `manage_options` can access Yuna Helper settings and catalog.
- Input is sanitized before save.
- GitHub responses are cached for 10 minutes to reduce API calls.
- If no release exists for a repo, it will not show in the catalog.

---

## 6) Roadmap suggestions

If you want full one-click in-dashboard upgrades next:

- Add direct WP upgrader integration using release ZIP URLs.
- Add signed package/hash verification.
- Add multi-channel releases (stable/beta).
- Add compatibility checks (WP/PHP minimums) per plugin.
