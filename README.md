# Yuna WordPress Helper

Simple control center for your `yuna-` plugin repositories.

## How it works

1. Install and activate the helper plugin.
2. On activation, it opens the helper admin page.
3. Enter only your GitHub token.
4. Helper lists repositories that contain `yuna-` and have a latest release.
5. From the list, you can:
   - install/update + activate plugin,
   - enable/disable managed status,
   - enable auto-update (optional, off by default).

## Required GitHub token

Use a fine-grained token with read access to repositories/releases.

## Helper update button

At the bottom of the page there is a helper section:

**Need to update the helper? Click here.**

It runs a helper plugin update check and executes the update if available.

## AI onboarding instructions URL

`https://maximebellefleur.com/yunadesign/helper/PLUGIN_ONBOARDING_AI.md`
