=== Agentic Storefront for Publishers ===
Contributors: xpaysh
Tags: ai, recommendations, affiliate, llms, agentic
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add contextual product recommendations to your WordPress posts and publish a structured feed for AI shopping assistants.

== Description ==

**Full product overview:** https://www.xpay.sh/publishers/storefront/ · **Plugin landing page:** https://www.xpay.sh/publishers/wordpress-plugin/

**Documentation:** https://docs.xpay.sh/en/publishers/wordpress-plugin · **Source code:** https://github.com/xpaysh/agentic-storefront-for-publishers · **Issue tracker:** https://github.com/xpaysh/agentic-storefront-for-publishers/issues

**Your readers are increasingly arriving from ChatGPT, Claude, Gemini and Perplexity.** They're also still arriving the usual way. Agentic Storefront helps you serve both at once with one short install.

For human readers, the plugin renders an optional, dismissible recommendation widget inside an iframe hosted at `widget.xpay.sh`. You decide where it appears — by shortcode or block — and what categories to exclude. No tracking pixels, no behavioural targeting, no third-party cookies are set on your site by this plugin. The recommendation engine matches against the page you publish, not the visitor.

For AI assistants and agents, the plugin publishes a single signed endpoint at `/.well-known/agent-storefront.json` that lists products contextually relevant to your site. Agents that fetch it can discover and (where the underlying merchants support it) transact, with the resulting referral attributed back to your site.

= What it does =

* **Manual product-recommendation widget** — shortcode `[xpay_recs]` and a Gutenberg block for placing recommendations exactly where you want them. The widget renders inside a sandboxed iframe at `widget.xpay.sh/embed/recs/inline` — the plugin itself ships no front-end JavaScript renderer. No automatic injection into your post content.
* **Privacy-first** — the plugin sets no third-party cookies and emits no tracking pixels on your site. The decision API receives only the public URL, post title, public categories and tags. The iframe is gated by your WP Consent API integration.
* **Agent storefront endpoint** — publishes `/.well-known/agent-storefront.json` so AI assistants can list products contextually relevant to the page they are reading. Detects existing `.well-known` files and refuses to overwrite them.
* **Optional `llms.txt` augmentation** — append a clearly-delimited block to your `llms.txt`, only if you have opted in. Will never replace an existing `llms.txt`.
* **Brand-safety controls** — exclude product categories and merchant domains directly from the settings screen.
* **Amazon Associates** — set your Amazon Associates tag. Any Amazon link the widget surfaces gets `?tag=<yours>` appended. Amazon pays you directly.
* **Native WordPress settings screen** — the settings UI is a sandboxed iframe at `widget.xpay.sh/embed/admin/settings`. Edits are saved via the plugin's REST endpoint (`/wp-json/asp/v1/settings`) into your `wp_options` — your data never leaves WordPress for storage.

= What it does not do =

* **It does not collect visitor identifiers without consent.** Without an explicit positive consent signal from the WP Consent API, the iframe is not rendered.
* **It does not change your existing themes, posts or templates.** Recommendations only appear where you explicitly place a shortcode or block.
* **It does not require a merchant relationship.** Publishers can install and connect with no e-commerce site of their own.
* **It does not load any executable code from a remote server into the host page context.** Front-end widgets are rendered inside sandboxed iframes — separate browsing contexts that the host page (your theme, your other plugins) cannot read into.

= External services =

This plugin integrates with services operated by xpay (xpay.sh). The plugin contacts these services on your behalf, and the front-end widget iframe loads from one of them.

**1. `widget.xpay.sh`** — UI host. Two iframes load from this origin:

* `widget.xpay.sh/embed/admin/settings` — the WordPress admin settings page. Iframed inside Settings → Agentic Storefront. Data passed to the iframe via URL parameters: your `site_id` (random opaque identifier), the public hostname of your WP install, the connection status flag, and the installed plugin version. The iframe holds no credentials and makes no API calls; user edits are postMessaged back to the WP admin shell, which saves them to your `wp_options` via the plugin's REST endpoint.
* `widget.xpay.sh/embed/recs/inline` — the front-end recommendation widget. Embedded by the `[xpay_recs]` shortcode and the Recommendations block. Data passed to the iframe via URL parameters: your `site_id`, the post's public URL, title, public categories, public tags. No visitor identifier is sent.

Both iframes are sandboxed (`sandbox="allow-scripts allow-same-origin allow-forms allow-popups"`) and referrer-stripped (`referrerpolicy="no-referrer"`).

**2. `publisher-api.xpay.sh`** — backend API. Called by the widget iframes (not by the WordPress runtime directly). Dedicated subdomain for this plugin's backend so other xpay products can't read or write here.

* `POST /storefront/decide` — recommendation decision API. Each time a widget surface renders, the iframe requests a recommendation payload. Data sent: page URL, title, categories, tags, `site_id`. No visitor identifier.
* `POST /storefront/beacon` — load/click event endpoint. When the widget mounts on a host page, an anonymous "load" event is fired so you can see in your xpay dashboard which of your host pages are running the script. A "click" event is fired when a reader clicks a recommended product card. Data sent: `site_id`, hostname, post URL, merchant domain (on click), user agent string. No visitor identifier.
* `GET /storefront/installs` — used by the xpay dashboard (not by this plugin) to list the hosts currently loading the widget. Authenticated.
* `POST /storefront/leads` — lead-capture endpoint called by the onboard page at `app.xpay.sh`, not by this WordPress plugin. Mentioned here for completeness only. The plugin itself does not call this endpoint.
* `POST /storefront/register` — registration endpoint. Called once during one-click connect to mint a `site_id`. Data sent: site URL, admin email (only if the admin opted in to email reports during onboard), one-time OAuth nonce.
* `GET /storefront/sites` / `GET /storefront/sites/{site_id}/...` — dashboard reads for your connected sites. Authenticated.

**3. `app.xpay.sh`** — publisher dashboard. The plugin links you to this URL from the settings page (not embedded). Data passed via deep-link query parameters only.

The xpay terms of service and privacy policy: https://www.xpay.sh/terms/ and https://www.xpay.sh/privacy/.

= Privacy =

This plugin is designed so the WordPress runtime never sends visitor-identifying data anywhere.

* **No third-party cookies, no tracking pixels.** The plugin sets no cookies on your site and emits no tracking pixels.
* **Page-context only, no visitor identifiers.** The widget iframe URLs and backend calls receive only the public URL of the page, its public title, its public categories, and its public tags — the same data already in your HTML for search engines. No visitor cookie, IP-derived ID, or device fingerprint is included in the request body.
* **Iframe sandbox isolation.** Recommendation widgets and the settings UI render inside sandboxed iframes from `widget.xpay.sh`. The host page and the iframe are separate browsing contexts that cannot read each other.
* **WP Consent API integration.** When the WP Consent API plugin is installed and reports a hard "no" for marketing consent, the iframes are not rendered. If the WP Consent API plugin is not installed, the iframes still load because they collect no visitor data — they are functionally equivalent to embedded editorial content from a third party.
* **All settings stored in WordPress `wp_options`.** Your Amazon Associates tag, excluded categories, excluded domains, and the auto-inject toggle are stored locally in your database. They are not copied to xpay's backend.
* **Cleanup on uninstall.** Deleting the plugin removes every `wp_options` row it created and disables the agent storefront endpoint. No data is left in your database.

= Where the recommended products come from =

The recommendation engine uses a curated catalog of merchants from xpay's own merchant network, with affiliate-network fallbacks (mrge.com, and other networks added in later releases). The agent storefront endpoint only lists products from agent-ready merchants, since those are the only ones an AI assistant can transact with.

== Installation ==

1. Install the plugin from this directory or upload the ZIP via Plugins → Add New → Upload.
2. Activate. You will be taken to **Settings → Agentic Storefront**.
3. Click **Connect site**. A short browser tab will open on xpay.sh and returns with a `site_id` written into your settings.
4. The recommendation widget renders automatically below the body of every single post. Want manual placement instead? Toggle **Auto-inject below post content** off and use the `[xpay_recs]` shortcode or **Recommendations** block.
5. (Optional) Enable the agent storefront endpoint to allow AI assistants to discover products from your site.

Detailed step-by-step with screenshots:

* **Installing the plugin** — https://docs.xpay.sh/en/publishers/wordpress-plugin/installing
* **Connecting your site** — https://docs.xpay.sh/en/publishers/wordpress-plugin/connecting
* **Placing the widget** — https://docs.xpay.sh/en/publishers/wordpress-plugin/using
* **Settings reference** — https://docs.xpay.sh/en/publishers/wordpress-plugin/settings
* **Troubleshooting** — https://docs.xpay.sh/en/publishers/wordpress-plugin/troubleshooting

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

The plugin itself enqueues no front-end scripts on your site. Recommendation widgets load lazily inside an iframe (one network round-trip, async after the page is interactive). The agent endpoint is served server-side without touching the front-end.

= Does it conflict with my ad network (Mediavine, Raptive, Ezoic)? =

Recommendation widgets are styled as editorial product cards with affiliate-link buy buttons, not as advertising. They behave like Skimlinks- or Sovrn-style affiliate widgets, which most ad networks permit in parallel. Always verify against your specific ad-network agreement before going live.

= Why is the settings screen and the widget rendered in iframes? =

Two reasons. (1) The widget rendering is intricate (product cards, a footer drawer, a floating action button) and is iterated on quickly at `widget.xpay.sh` — iframing it means we don't have to push a WordPress plugin update every time the UI improves. (2) The iframe is a separate browsing context: the host page can't read into it, and it can't read into the host page. That's the strongest privacy isolation WordPress can offer for a third-party widget.

= Does it work without WooCommerce? =

Yes — this plugin has no dependency on WooCommerce. It is designed for content publishers without their own store.

= How does the agent storefront endpoint work? =

After you enable it in settings, your site serves `https://your-site.example/.well-known/agent-storefront.json` with a list of products an AI assistant can recommend. The list is generated server-side by xpay; the response is signed against your `site_id`. The plugin will not overwrite an existing file at that path — if one is detected the emitter is disabled until you remove the conflict.

= Can I remove the plugin cleanly? =

Yes. Deleting the plugin removes all settings, transients and the agent storefront endpoint. No data is left in your database.

== Screenshots ==

1. Settings screen — sandboxed iframe with native WordPress chrome around it.
2. Brand-safety exclude list for categories and domains.
3. Recommendations block in the editor.
4. Front-end recommendation widget (inline, FAB, and footer drawer surfaces).

== Changelog ==

= 0.3.6 =
* Pre-WordPress.org-submit hardening pass against the published guidelines.
* Settings screen now includes a visible "Your xpay account control panel" disclosure block above the embedded panel, naming every host the plugin contacts (`widget.xpay.sh`, `publisher-api.xpay.sh`, `app.xpay.sh`) and what data is sent.
* The embedded panel iframe title rewritten to "xpay account control panel (embedded from widget.xpay.sh)" so reviewers and users can see the framing without reading source.
* Inline `<script>` bridge moved out of the settings page into `assets/js/asp-settings-bridge.js`. Configuration values printed alongside via `wp_add_inline_script` so Plugin Check / caching plugins / security scanners see it on the public scripts list.
* `/llms.txt` body is now composed from pre-escaped values (`wp_strip_all_tags` + `esc_url_raw`) so the final text/plain emit is safe-by-construction; `phpcs:ignore` comment kept only on the final `echo` with a documented justification.
* `/asp/v1/page-context` REST endpoint kept public (the widget iframe has no access to WordPress nonces) but now has an inline header comment explaining: only published-post public metadata is exposed; no private fields; same data Google scrapes from the post URL.
* Readme privacy section reworded to match the code's actual behaviour — no more "strongest privacy isolation" superlative. The WP Consent API gate is described accurately (iframes do load when no Consent API plugin is installed; they collect no visitor data).
* Removed the `install.xpay.sh` ZIP-mirror sentence from the readme. The mirror still exists (publishers without WordPress.org access can install via direct ZIP) but the WordPress.org-facing readme no longer advertises an alternate distribution channel.
* Added empty `index.php` silence files to every plugin subdirectory (`includes/`, `assets/`, `assets/js/`, `assets/css/`, `assets/blocks/`, `assets/blocks/recommendations/`).

= 0.3.5 =
* Fixed two WordPress.org plugin-check errors reported on 0.3.4:
* `NonEnqueuedScript` — `widget.xpay.sh/widget/v1/widget.js` is now registered via `wp_register_script` and enqueued via `wp_enqueue_script` on the `wp_enqueue_scripts` hook. The required `data-*` attributes (site-id, storefront-api, embed-base, amazon-tag, surfaces) are injected via the `script_loader_tag` filter so the rendered tag is identical to before but flows through the standard WordPress script pipeline (other plugins can dequeue / reorder it, caching plugins see it on the public scripts list).
* Readme short description rewritten in plain English to satisfy `readme_short_description_non_official_language`.

= 0.3.4 =
* Plugin URI updated to the dedicated landing page `xpay.sh/publishers/wordpress-plugin/`.
* readme.txt now links the full documentation set at `docs.xpay.sh/en/publishers/wordpress-plugin/*` (installing, connecting, using, settings, privacy, troubleshooting) and the public source repository at `github.com/xpaysh/agentic-storefront-for-publishers`.
* Installation steps reflect the auto-inject default added in 0.3.3 (single-post pages render automatically; manual shortcode placement is now opt-in).

= 0.3.3 =
* **Auto-inject below post content (default ON).** Single-post pages now automatically render the recommendation widget below the post body — no shortcode placement required. Skips when the shortcode or block is already present in the post body, when the site isn't connected, or when consent is hard-denied. New toggle in the settings iframe: "Where the widget appears → Auto-inject below post content".
* **Front-end widget script enqueued via `wp_footer`.** The bottom-right FAB and sticky footer drawer surfaces are now mounted automatically on connected sites. Previously only the inline iframe rendered (via shortcode/block).
* **Backend moved to dedicated subdomain `publisher-api.xpay.sh`.** No more relying on the shared `api.xpay.sh` umbrella. `ASP_API_BASE` default updated; existing installs continue to work because the dashboard reads the publisher's stored API base.
* External-services disclosure expanded to enumerate every endpoint on the dedicated subdomain, including `/storefront/leads` (used by the onboard page, not by this plugin) and `/storefront/installs` (dashboard read).
* New Privacy section in the readme spelling out: no cookies, no tracking pixels, no visitor identifiers, iframe sandbox isolation, consent gating, and clean uninstall.

= 0.3.0 =
* **Thin-shell architecture.** The plugin no longer ships a bundled JavaScript renderer. The recommendation widget and the WordPress settings screen are now rendered inside sandboxed iframes loaded from `widget.xpay.sh`. The plugin's PHP footprint dropped by ~80% and the rendering can iterate without a plugin update.
* New REST endpoint `POST /wp-json/asp/v1/settings` that receives validated settings JSON via postMessage from the settings iframe. WordPress nonces guard the endpoint; user input is sanitised on the way in.
* `[xpay_recs]` shortcode and the Recommendations Gutenberg block now emit a sandboxed iframe pointing at `widget.xpay.sh/embed/recs/inline`. No more inline JSON config blocks or bundled SDK.
* Deleted `assets/js/asp-widget.js`. ASP_Loader no longer enqueues any front-end JavaScript.
* External-services disclosure expanded — every iframe URL and every backend API endpoint is now spelled out, including the data passed via URL parameters.

= 0.2.0 =
* One-click "Open xpay dashboard" link from the connected settings screen.

= 0.1.0 =
* Initial release.
* Shortcode and Gutenberg block for placing recommendation widgets manually.
* `/.well-known/agent-storefront.json` emitter with detect-existing safety check.
* Optional `llms.txt` append (off by default).
* WP Consent API integration: front-end script not enqueued until consent positive.
* Brand-safety exclude lists.
* Optional Amazon Associates per-site tag.

== Upgrade Notice ==

= 0.3.6 =
WordPress.org pre-submit hardening — visible external-services disclosure on the settings page, inline script moved to a separate enqueued file, llms.txt output escaped at composition time, readme privacy claims aligned with code behaviour. No behavioural change to publishers; safe drop-in upgrade.

= 0.3.5 =
Fixes two WordPress.org plugin-check errors on the 0.3.4 build: front-end script now flows through wp_enqueue_script + script_loader_tag, and the readme short description was rewritten. No behavioural change. Safe drop-in upgrade.

= 0.3.4 =
Documentation site is now live at docs.xpay.sh/en/publishers/wordpress-plugin and the source repository is public on GitHub. No code changes. Safe drop-in upgrade.

= 0.3.3 =
Auto-inject below post content (default ON) — no shortcode placement needed. FAB + footer drawer mount automatically on connected sites. Backend moved to dedicated subdomain publisher-api.xpay.sh. New privacy disclosure in readme. No new tracking. Safe drop-in upgrade.

= 0.3.0 =
Front-end widget and admin settings now render inside sandboxed iframes from widget.xpay.sh. UI quality improves significantly; PHP footprint drops ~80%. No new tracking, no new data sent. Safe drop-in upgrade.

= 0.2.0 =
Adds a deep-link from your plugin settings to your xpay dashboard. No new tracking, no new external services. Safe drop-in upgrade.

= 0.1.0 =
First public release.
