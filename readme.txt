=== Agentic Storefront for Publishers ===
Contributors: xpaysh
Tags: ai, recommendations, affiliate, llms, agentic
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Contextual product recommendations for content publishers + an agent-readable storefront endpoint so AI assistants can discover products from your posts.

== Description ==

**Your readers are increasingly arriving from ChatGPT, Claude, Gemini and Perplexity.** They're also still arriving the usual way. Agentic Storefront helps you serve both at once with one short install.

For human readers, the plugin adds an optional, dismissible recommendation widget powered by a curated catalog of merchants. You decide where it appears — by shortcode or block — and what categories to exclude. No tracking pixels, no behavioural targeting, no third-party cookies. The recommendation engine matches against the page you publish, not the visitor.

For AI assistants and agents, the plugin publishes a single signed endpoint at `/.well-known/agent-storefront.json` that lists products contextually relevant to your site. Agents that fetch it can discover and (where the underlying merchants support it) transact, with the resulting referral attributed back to your site.

The plugin is built for the WordPress.org plugin guidelines first: all JavaScript is bundled inside the plugin (no remotely-loaded code), and no script tag is enqueued until your consent banner authorises it via the WP Consent API.

= What it does =

* **Manual product-recommendation widget** — shortcode `[xpay_recs]` and a Gutenberg block for placing recommendations exactly where you want them. No automatic injection into your post content in this release.
* **Privacy-first** — no third-party cookies, no behavioural tracking, no visitor identifier set unless your consent manager explicitly grants it. The decision API receives only the public URL, post title, categories and tags.
* **Agent storefront endpoint** — publishes `/.well-known/agent-storefront.json` so AI assistants can list products contextually relevant to the page they are reading. Detects existing `.well-known` files and refuses to overwrite them.
* **Optional `llms.txt` augmentation** — append a clearly-delimited block to your `llms.txt`, only if you already have one or have opted in. Will never replace an existing `llms.txt`.
* **Brand-safety controls** — exclude product categories and merchant domains directly from the settings screen. Stored locally; sent only with your `site_id` when the decision API runs.
* **Earnings dashboard** — counts of impressions, agent-fetches and accruing affiliate balance shown in your admin screen.

= What it does not do =

* **It does not collect visitor identifiers without consent.** Without an explicit positive consent signal from the WP Consent API, the front-end script is not even enqueued.
* **It does not change your existing themes, posts or templates.** Recommendations only appear where you explicitly place a shortcode or block.
* **It does not require a merchant relationship.** Publishers can install and connect with no e-commerce site of their own.
* **It does not load any code from a remote server.** All JavaScript and CSS that runs on your pages is shipped inside this plugin.

= External services =

This plugin contacts the following xpay-operated services. Each call carries the site\'s `site_id` (a random opaque identifier created by this plugin on first connect) and signed authentication.

* **`api.xpay.sh`** — recommendation decision API. On each rendered widget or agent-fetch, the plugin (or the agent) requests a recommendation payload. Data sent: the public URL of the page, post title, public categories and tags, and the site\'s `site_id`. No visitor data is sent.
* **`api.xpay.sh`** — registration endpoint. Called once during one-click connect to mint a `site_id` and tracking key. Data sent: site URL, admin email (only if the admin opts in to email reports), and a one-time OAuth nonce.
* **`api.xpay.sh`** — beacon endpoint. Optional; records impression and click counts so the admin dashboard can display them. Disabled until the publisher explicitly enables the "Earnings dashboard" option.

The xpay terms of service and privacy policy: https://www.xpay.sh/terms/ and https://www.xpay.sh/privacy/.

= Where the recommended products come from =

The recommendation engine uses a curated catalog of merchants from xpay\'s own merchant network (the same network used by other xpay products), with affiliate-network fallbacks (mrge.com — the publisher\'s default affiliate partner — and other affiliate networks added in later releases). The agent storefront endpoint only lists products from agent-ready merchants, since those are the only ones an AI assistant can transact with.

== Installation ==

1. Install the plugin from this directory or upload the ZIP via Plugins → Add New → Upload.
2. Activate. You will be taken to **Settings → Agentic Storefront**.
3. Click **Connect site**. A short browser tab will open on xpay.sh and return with a `site_id` written into your settings.
4. Place the recommendations widget where you want it — either by adding the shortcode `[xpay_recs]` to a post, or by inserting the **Recommendations** block in the block editor.
5. (Optional) Enable the agent storefront endpoint to allow AI assistants to discover products from your site.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

The front-end loader is a few kilobytes and is only enqueued when (a) a recommendations widget is on the page, and (b) your visitor consent manager has authorised it. The recommendation API is called asynchronously after the page is interactive.

= Does it conflict with my ad network (Mediavine, Raptive, Ezoic)? =

Recommendation widgets are styled as editorial product cards with affiliate-link buy buttons, not as advertising. They behave like Skimlinks- or Sovrn-style affiliate widgets, which most ad networks permit in parallel. Always verify against your specific ad-network agreement before going live.

= Does it work without WooCommerce? =

Yes — this plugin has no dependency on WooCommerce. It is designed for content publishers without their own store.

= How does the agent storefront endpoint work? =

After you enable it in settings, your site serves `https://your-site.example/.well-known/agent-storefront.json` with a list of products an AI assistant can recommend. The list is generated server-side by xpay; the response is signed against your `site_id`. The plugin will not overwrite an existing file at that path — if one is detected the emitter is disabled until you remove the conflict.

= Can I remove the plugin cleanly? =

Yes. Deleting the plugin removes all settings, transients and the agent storefront endpoint. No data is left in your database.

== Screenshots ==

1. Settings screen with one-click connect.
2. Brand-safety exclude list for categories and domains.
3. Recommendations block in the editor.
4. Front-end recommendation card (default theme).

== Changelog ==

= 0.1.0 =
* Initial release.
* Shortcode and Gutenberg block for placing recommendation widgets manually.
* `/.well-known/agent-storefront.json` emitter with detect-existing safety check.
* Optional `llms.txt` append (off by default).
* WP Consent API integration: front-end script not enqueued until consent positive.
* Brand-safety exclude lists.

== Upgrade Notice ==

= 0.1.0 =
First public release.
