=== xpay✦ Agentic Commerce for Publishers ===
Contributors: xpaysh
Tags: ai, recommendations, affiliate, llms, agentic
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add contextual product recommendations to your WordPress posts and publish an agent-readable product feed for AI shopping assistants.

== Description ==

**Plugin landing page:** https://www.xpay.sh/publishers/wordpress-plugin/ · **Documentation:** https://docs.xpay.sh/en/publishers/wordpress-plugin · **Source code:** https://github.com/xpaysh/xpay-agentic-commerce-for-publishers

**Your readers are increasingly arriving from ChatGPT, Claude, Gemini and Perplexity.** They are also still arriving the usual way. xpay✦ Agentic Commerce helps you serve both at once.

For human readers, the plugin loads a lightweight recommendation widget (a floating button + a footer drawer) on your connected site — install once, works on every page, no shortcode required. You can narrow the widget to a subset of paths or disable site-wide loading entirely in the settings. For inline placement inside a specific post, use the `[xpay_recs]` shortcode or the Recommendations Gutenberg block. The plugin never modifies your post content directly — recommendations live in a sandboxed iframe hosted at `widget.xpay.sh`, sets no third-party cookies, and uses no behavioural targeting.

For AI assistants and agents, the plugin publishes a single endpoint at `/.well-known/agent-storefront.json` that lists products contextually relevant to your site. Agents that fetch it can discover and (where the underlying merchants support it) transact, with the resulting referral attributed back to your site.

= What it does =

* **Site-wide widget (floating button + footer drawer)** — loads on every page of your connected site by default. Disable site-wide loading entirely, or narrow it to matching paths only, from Settings → xpay Agentic Commerce → "Where the widget loads". URL patterns support `*` wildcards (PostHog-style).
* **Inline placement** — shortcode `[xpay_recs]` and a Gutenberg block for placing a product-card grid inside a specific post. Independent of the site-wide widget. The plugin never modifies post content via `the_content` — placement is always explicit.
* **Privacy-first** — the plugin sets no third-party cookies and emits no tracking pixels. The decision API receives only the public URL, post title, public categories and tags. Personalization is off unless you turn it on and a Consent API plugin reports positive consent.
* **Agent storefront endpoint** — publishes `/.well-known/agent-storefront.json` so AI assistants can list products contextually relevant to the page they are reading. Detects existing `.well-known` files and refuses to overwrite them.
* **Optional `llms.txt` augmentation** — append a clearly-delimited block to your `llms.txt`, only if you have opted in. Never replaces an existing `llms.txt`.
* **Brand-safety controls** — exclude product categories and merchant domains directly from the native settings screen.
* **Amazon Associates** — set your Amazon Associates tag. Any Amazon link the widget surfaces gets `?tag=<yours>` appended. Amazon pays you directly.
* **Native WordPress settings screen** — all configuration happens inside a standard wp-admin settings page (Settings → xpay Agentic Commerce). No remote UI, no embedded admin iframe.

= What it does not do =

* **It does not modify your post content.** The plugin never hooks `the_content` or rewrites your post bodies. The site-wide widget lives in page chrome (floating button + drawer); inline placement requires an explicit shortcode or block.
* **It does not collect visitor identifiers.** The plugin sets no cookies on your site and emits no tracking pixels.
* **It does not change your existing themes, posts or templates.**
* **It does not require a merchant relationship.** Publishers can install and connect with no e-commerce site of their own.

= External services =

This plugin contacts services operated by xpay (xpay.sh).

**1. `publisher-api.xpay.sh`** — backend API.

* `POST /storefront/decide` — recommendation decision API. The widget iframe (front-end) calls this when it renders. Data sent: page URL, title, categories, tags, `site_id`. No visitor identifier.
* `POST /storefront/beacon` — load/click event endpoint. The widget iframe fires this anonymously when it mounts (load) and when a reader clicks a product card (click). Data sent: `site_id`, hostname, post URL, merchant domain (on click), user-agent string. No visitor identifier.
* `POST /storefront/register` — registration endpoint. Called once from the `app.xpay.sh` onboard page during one-click connect to mint a `site_id`.
* `GET /storefront/agent-card/{site_id}` — server-to-server call from your WordPress install to build the `/.well-known/agent-storefront.json` response.
* `GET /storefront/sites` — used by the publisher dashboard at `app.xpay.sh`, not by this plugin.

**2. `widget.xpay.sh`** — sandboxed iframe host for the front-end widget. Loaded only on posts where you place the `[xpay_recs]` shortcode or the Recommendations block, and only when consent allows. Data passed via URL parameters: `site_id`, post URL, title, public categories, public tags. No visitor identifier.

**3. `app.xpay.sh`** — publisher dashboard. Opened in a new tab from the settings page (a button labelled "Open xpay dashboard"). Never embedded.

The xpay terms of use and privacy policy: https://www.xpay.sh/legal/terms-of-use/ and https://www.xpay.sh/legal/privacy-policy/.

= Privacy =

* **No third-party cookies, no tracking pixels.** The plugin sets no cookies and emits no tracking pixels on your site.
* **Page-context only, no visitor identifiers.** The decision API and beacons receive only the public URL of the page, its public title, and its public categories and tags — the same data already in your HTML for search engines.
* **Iframe sandbox isolation.** The front-end widget renders inside a sandboxed iframe loaded from `widget.xpay.sh`. The host page and the iframe are separate browsing contexts that cannot read each other.
* **WP Consent API integration.** When the WP Consent API plugin is installed and reports a hard "no" for marketing consent, the widget iframe does not render.
* **All settings stored locally.** Your Amazon Associates tag, excluded categories, excluded domains and toggles are stored in WordPress `wp_options`. They are not copied to xpay's backend.
* **Cleanup on uninstall.** Deleting the plugin removes every `wp_options` row it created and disables the agent storefront endpoint.

= Where the recommended products come from =

The recommendation engine uses a curated catalog of merchants from xpay's own merchant network, with affiliate-network fallbacks. The agent storefront endpoint only lists products from agent-ready merchants, since those are the only ones an AI assistant can transact with.

== Installation ==

1. Install the plugin from this directory or upload the ZIP via Plugins → Add New → Upload.
2. Activate. You will be taken to **Settings → xpay Agentic Commerce**.
3. Click **Connect site**. A new browser tab opens on xpay.sh and returns you here with a `site_id` written into your settings.
4. To show recommendations on a post, add the `[xpay_recs]` shortcode or insert the **Recommendations** block in the editor. The widget renders only where you place it.
5. (Optional) Enable the agent storefront endpoint to allow AI assistants to discover products from your site.

Detailed step-by-step with screenshots:

* **Installing the plugin** — https://docs.xpay.sh/en/publishers/wordpress-plugin/installing
* **Connecting your site** — https://docs.xpay.sh/en/publishers/wordpress-plugin/connecting
* **Placing the widget** — https://docs.xpay.sh/en/publishers/wordpress-plugin/using
* **Settings reference** — https://docs.xpay.sh/en/publishers/wordpress-plugin/settings
* **Troubleshooting** — https://docs.xpay.sh/en/publishers/wordpress-plugin/troubleshooting

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

The plugin itself enqueues no front-end scripts unless a post actually contains the shortcode or block. The widget iframe loads lazily — one network round-trip, async after the page is interactive. The agent endpoint is served server-side without touching the front-end.

= Does it conflict with my ad network (Mediavine, Raptive, Ezoic)? =

The widget renders as editorial product cards with affiliate-link buy buttons, not as advertising, and only appears where you explicitly place it. Most ad networks permit such widgets in parallel. Always verify against your specific ad-network agreement before going live.

= Why is the front-end widget rendered in an iframe? =

Two reasons. (1) The widget UI iterates quickly at `widget.xpay.sh` — iframing means we don't ship a WordPress plugin update every time the UI improves. (2) The iframe is a separate browsing context: the host page can't read into it, and it can't read into the host page. That's strong privacy isolation for a third-party recommendation widget.

= Does it work without WooCommerce? =

Yes — this plugin has no dependency on WooCommerce. It is designed for content publishers without their own store.

= How does the agent storefront endpoint work? =

After you enable it in settings, your site serves `https://your-site.example/.well-known/agent-storefront.json` with a list of products an AI assistant can recommend. The list is generated server-side. The plugin will not overwrite an existing file at that path — if one is detected the emitter stays silent until you remove the conflict.

= Can I remove the plugin cleanly? =

Yes. Deleting the plugin removes all settings, transients and the agent storefront endpoint. No data is left in your database.

== Screenshots ==

1. Native WordPress settings screen with brand-safety, Amazon Associates and emitter toggles.
2. Recommendations block in the editor.
3. Front-end recommendation widget rendered by the shortcode.

== Changelog ==

= 0.4.3 =
* New "Where the widget loads" settings section: master on/off toggle (default on), "Show only on these paths" include rules, and "Never show on these paths" exclude rules. Wildcards `*` and `?` are supported via fnmatch, matched against the request path.
* Connect-return handler accepts both `xpayacp_*` and legacy `asp_*` query parameters from the xpay onboard page.
* `$_GET` index checks refactored to explicit branches so static analysers can verify each access.
* `$xpayacp_options` and `$xpayacp_opt` variables in `uninstall.php` properly prefixed.

= 0.4.0 =
* **Renamed to xpay✦ Agentic Commerce for Publishers.** New slug `xpay-agentic-commerce-for-publishers`. The previous working name overlapped with Automattic's Storefront theme.
* **Native WordPress settings screen.** The admin settings screen is now a standard wp-admin page built with the Settings API. The embedded `widget.xpay.sh/embed/admin/settings` iframe has been removed; no remote UI is loaded into wp-admin.
* **Auto-injection of the widget removed.** The widget no longer appends itself to post content. It renders only where you place the `[xpay_recs]` shortcode or the Recommendations block. Existing sites with the auto-inject toggle previously on must add the shortcode or block where they want the widget.
* **Signed `/page-context` REST endpoint.** The widget iframe now signs its `page-context` requests with an HMAC derived from the per-site secret minted at activation. The endpoint no longer accepts unauthenticated reads.
* **Tightened admin handlers.** The disconnect action now runs through a nonced `admin-post.php` handler with an explicit `manage_options` capability check.
* All function, class, constant, option, transient and shortcode-internal prefixes consolidated under `xpayacp_` / `XPAYACP_`.

= 0.3.6 =
* Pre-WordPress.org-submit hardening pass against the published guidelines.
* `/llms.txt` body is now composed from pre-escaped values.
* Readme privacy section reworded to match the code's actual behaviour.
* Added empty `index.php` silence files to every plugin subdirectory.

= 0.3.5 =
* Front-end widget script now flows through `wp_register_script` / `wp_enqueue_script` / `script_loader_tag`.
* Readme short description rewritten in plain English.

= 0.3.4 =
* Plugin URI updated to the dedicated landing page.
* Documentation set published at `docs.xpay.sh/en/publishers/wordpress-plugin/*`.

= 0.3.0 =
* Thin-shell architecture — front-end widget runs inside a sandboxed iframe.

= 0.2.0 =
* One-click "Open xpay dashboard" link from the connected settings screen.

= 0.1.0 =
* Initial release.
* Shortcode and Gutenberg block for placing recommendation widgets manually.
* `/.well-known/agent-storefront.json` emitter with detect-existing safety check.
* Optional `llms.txt` append (off by default).
* WP Consent API integration.
* Brand-safety exclude lists.
* Optional Amazon Associates per-site tag.

== Upgrade Notice ==

= 0.4.3 =
New URL-pattern targeting for the site-wide widget: narrow to specific paths or disable entirely from Settings.

= 0.4.1 =
Connect-return now accepts both new and legacy query parameters from the onboard page. Plugin-check warnings cleared.

= 0.4.0 =
Renamed plugin (new slug). Native settings screen replaces the embedded iframe. Auto-inject removed — use shortcode or block to place the widget.

= 0.3.6 =
Pre-WP.org-submit polish. No behavioural change.
