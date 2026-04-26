# Yuna WordPress Helper

Simple control center for your `yuna-` plugin repositories.

## How it works

1. Install and activate the helper plugin.
2. On activation, it opens the helper admin page.
3. Enter your GitHub token (for GitHub API) and your client access token details (for entitlement checks).
4. Before protected actions, helper verifies access at:
   - `POST https://maximebellefleur.com/yunadesign/helper/api/verify-token`
5. Helper lists repositories matching `maximebellefleur/yuna-*` and with a latest release.
5. From the list, you can:
   - install/update + activate plugin,
   - enable/disable managed status,
   - enable auto-update (optional, off by default).

## Token minisite responsibility (strict boundary)

The token minisite is **only** an entitlement API:
- token creation in minisite admin
- token validation
- domain/client entitlement checks
- returns `{ "valid": true }` or `{ "valid": false, "reason": "..." }`

The helper plugin handles installation, activation, release checks, ZIP download/update flows.

## Required GitHub token

Use a fine-grained token with read access to repositories/releases.

## Helper update button

At the bottom of the page there is a helper section:

**Need to update the helper? Click here.**

It runs a helper plugin update check and executes the update if available.

## AI onboarding instructions URL

`https://maximebellefleur.com/yunadesign/helper/PLUGIN_ONBOARDING_AI.md`
