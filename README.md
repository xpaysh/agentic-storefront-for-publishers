# Agentic Storefront for Publishers

WordPress plugin: contextual product recommendations for content publishers + an agent-readable storefront endpoint so AI assistants can discover products from your site.

- Slug: `agentic-storefront-for-publishers`
- Author: xpay
- License: GPL-2.0-or-later
- WP.org submission target: yes
- Plan: `docs/jun/04/agentic-storefront-for-publishers-wp-plugin-plan-v2.md` in the parent `mvp/` workspace.

## Local layout

```
agentic-storefront-for-publishers/
├── agentic-storefront-for-publishers.php   main bootstrap
├── readme.txt                              WP.org canonical readme
├── uninstall.php                           clean settings on delete
├── includes/                               core PHP classes
│   ├── class-asp-plugin.php
│   ├── class-asp-client.php                HTTP client to api.xpay.sh
│   ├── class-asp-consent.php               WP Consent API gating
│   ├── class-asp-emitter-probe.php         detect-not-clobber probe
│   ├── class-asp-rest.php                  WP REST routes + /.well-known emitter
│   ├── class-asp-settings.php              admin UI (Settings API)
│   ├── class-asp-shortcode.php             [xpay_recs]
│   ├── class-asp-block.php                 Gutenberg block (server-rendered)
│   └── class-asp-loader.php                front-end script enqueue (consent-gated)
├── assets/
│   ├── js/asp-widget.js                    bundled vanilla Web Component
│   ├── css/asp-admin.css
│   └── blocks/recommendations/             block.json + editor script
├── languages/
└── scripts/
    └── build-zip.sh                        package for WP.org SVN trunk
```

## WP.org review safety

This v0.1 plugin is intentionally conservative:

- **All JavaScript is bundled** (no remote SDK, no `cdn.xpay.sh` runtime fetch).
- **No `the_content` auto-injection** — placement is by shortcode or block only.
- **No visitor identifier set without consent** — the front-end script is only enqueued when (a) a placeholder is on the page and (b) consent is allowed.
- **REST URLs use `rest_url()`** — never `home_url('/wp-json/...')`.
- **`/llms.txt` + `/.well-known/agent-storefront.json` detect-not-clobber** — refuses to overwrite existing emitters.
- **External services disclosed** in `readme.txt` with the exact endpoints and payloads.

## Build the WP.org ZIP

```
scripts/build-zip.sh
```

Produces `build/agentic-storefront-for-publishers.zip`, suitable for direct
upload via Plugins → Add New → Upload (and identical to what gets committed
to WP.org SVN trunk).
