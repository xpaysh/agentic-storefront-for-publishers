=== Agentic Storefront for Publishers ===
Contributors: xpaysh
Tags: ai, recommendations, affiliate, llms, agentic
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Contextual product cards on your posts plus an agent-readable storefront endpoint AI assistants can discover.

== Description ==

**Full plain-English overview:** https://www.xpay.sh/publishers/wordpress-plugin

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

**2. `api.xpay.sh`** — backend API. Called by the widget iframes (not by the WordPress runtime directly).

* Recommendation decision API. Each time the inline widget renders, the iframe requests a recommendation payload. Data sent: page URL, title, categories, tags, `site_id`. No visitor identifier.
* Beacon endpoint. Each time the widget mounts, an anonymous "load" event is fired so you can see in the xpay dashboard which of your host pages are running the script. Data sent: `site_id`, hostname, post URL, user agent string. No visitor identifier.
* Registration endpoint. Called once during one-click connect to mint a `site_id`. Data sent: site URL, admin email (if the admin opts in to email reports), one-time OAuth nonce.

**3. `app.xpay.sh`** — publisher dashboard. The plugin links you to this URL from the settings page (not embedded). Data passed via deep-link query parameters only.

The xpay terms of service and privacy policy: https://www.xpay.sh/terms/ and https://www.xpay.sh/privacy/.

= Where the recommended products come from =

The recommendation engine uses a curated catalog of merchants from xpay's own merchant network, with affiliate-network fallbacks (mrge.com, and other networks added in later releases). The agent storefront endpoint only lists products from agent-ready merchants, since those are the only ones an AI assistant can transact with.

== Installation ==

1. Install the plugin from this directory or upload the ZIP via Plugins → Add New → Upload.
2. Activate. You will be taken to **Settings → Agentic Storefront**.
3. Click **Connect site**. A short browser tab will open on xpay.sh and return with a `site_id` written into your settings.
4. Place the recommendations widget where you want it — either by adding the shortcode `[xpay_recs]` to a post, or by inserting the **Recommendations** block in the block editor.
5. (Optional) Enable the agent storefront endpoint to allow AI assistants to discover products from your site.

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

= 0.3.0 =
Front-end widget and admin settings now render inside sandboxed iframes from widget.xpay.sh. UI quality improves significantly; PHP footprint drops ~80%. No new tracking, no new data sent. Safe drop-in upgrade.

= 0.2.0 =
Adds a deep-link from your plugin settings to your xpay dashboard. No new tracking, no new external services. Safe drop-in upgrade.

= 0.1.0 =
First public release.
