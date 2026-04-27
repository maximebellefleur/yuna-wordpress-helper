# Yuna WordPress Helper

Simple control center for your `yuna-` plugin repositories.

## How it works

1. Install and activate the helper plugin.
2. On activation, it opens the helper admin page.
3. Enter the raw access token shown once by the minisite.
4. Before protected actions, helper verifies access at:
   - `POST https://maximebellefleur.com/yunadesign/helper/api/verify-token`
5. The verification response returns current authorized `yuna-` plugin repository links under `https://github.com/maximebellefleur/`, excluding `yuna-wordpress-helper`.
6. Helper lists verified repositories with a latest release.
7. From the list, you can:
   - install/update + activate plugin,
   - enable/disable managed status,
   - enable auto-update (optional, off by default).

## Token minisite responsibility (strict boundary)

The token minisite is **only** an entitlement API:
- token creation in minisite admin
- token validation
- domain/client entitlement checks
- returns `{ "valid": true, "repositories": [...] }` or `{ "valid": false, "reason": "..." }`

The helper sends this verification payload:

```json
{
  "token": "raw-token-shown-once",
  "site": "https://example.com"
}
```

Success response:

```json
{
  "valid": true,
  "repositories": [
    "https://github.com/maximebellefleur/yuna-stats",
    "https://github.com/maximebellefleur/yuna-vulnerabilities"
  ]
}
```

Failure response:

```json
{ "valid": false, "reason": "token_expired" }
```

Reason codes: `invalid_request`, `invalid_token`, `token_revoked`, `token_expired`, `domain_not_allowed`, `temporarily_locked`, `rate_limited`.

The helper plugin handles installation, activation, release checks, ZIP download/update flows.

No GitHub token is required in this helper plugin.

## Helper update button

At the bottom of the page there is a helper section:

**Need to update the helper? Click here.**

It runs a helper plugin update check and executes the update if available.

## AI onboarding instructions URL

`https://maximebellefleur.com/yunadesign/helper/PLUGIN_ONBOARDING_AI.md`

Give that URL to any AI or developer that needs to hook a future Yuna plugin into the core helper.
