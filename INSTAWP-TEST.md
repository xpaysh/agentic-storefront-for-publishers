# InstaWP / dev-stack test — v0.1.0

End-to-end smoke against the `xpay-publisher-storefront` dev backend.

## Dev backend

Stage `dev` deployed to AWS account `582760238698` (us-east-1) via `agentically` profile.

- Base URL: `https://1iiczxdfea.execute-api.us-east-1.amazonaws.com`
- Health: `GET /storefront/health`
- Catalog: 6 fixture products (4 xpay-deals, 2 mrge stubs)

## Install on InstaWP

1. Spin up a fresh WP site on InstaWP.
2. Upload `build/agentic-storefront-for-publishers.zip` via **Plugins → Add New → Upload Plugin**.
3. Activate.
4. Add to `wp-config.php` **above** the `/* That's all, stop editing! */` line:

   ```php
   define( 'ASP_API_BASE_OVERRIDE', 'https://1iiczxdfea.execute-api.us-east-1.amazonaws.com' );
   ```

   (InstaWP has a wp-config editor under **InstaWP magic** menu — or use the file manager.)

5. Click **Connect site**. The plugin redirects to
   `app.xpay.sh/onboard/publisher` (built in `xpay-app/src/app/onboard/publisher/`),
   which calls `/storefront/register` server-side, mints a `site_id`, and
   redirects back to the plugin settings page with `?asp_site_id=…` appended.
   The settings page auto-captures + persists it.

   **Local fallback (if `app.xpay.sh/onboard/publisher` isn't yet deployed):**

   Mint a `site_id` manually:
   ```bash
   curl -X POST https://1iiczxdfea.execute-api.us-east-1.amazonaws.com/storefront/register \
     -H 'Content-Type: application/json' \
     -d '{"site_url":"https://<your-instawp>.instawp.xyz","admin_email":"sri@xpay.sh"}'
   ```

   Then hit your WP admin with the returned id in the URL:
   `<wp-admin>/options-general.php?page=agentic-storefront-for-publishers&asp_site_id=<id>&asp_connected=1`

   The same auto-capture handler persists it.

   Pre-minted test site for `snowflake-quoll-134a4c.instawp.site`:
   `site_773571663617412c91`

## What to verify

| Surface | How | Expected |
|---|---|---|
| Shortcode | Drop `[xpay_recs]` into a post titled "Instant Pot Biryani Recipe" | Renders 3 product cards (Masala Dabba, Instant Pot Duo, Wild One harness) |
| Gutenberg block | Insert "Recommendations" block | Same render as shortcode |
| Agent card | `curl https://<your-instawp>.instawp.xyz/.well-known/agent-storefront.json` | Returns JSON with `xpay_deals` products only (mrge stubs filtered) |
| llms.txt | Toggle on in settings, then `curl <site>/llms.txt` | Append-only fenced block, doesn't clobber existing |

## Known gaps (v0.1.0)

- Decide currently returns a default-ranked card set; per-page ranking by title keywords is in `src/lib/ranking.js` (mock).
- No real xpay-deals or mrge.com integration — fixture catalog only.
- No x402 challenge layer on agent endpoint — JSON only.
- No L4 / cookies / personalization.

## Teardown

Removing the plugin via WP **Plugins → Delete** triggers `uninstall.php` which clears every option + transient. To clean up DDB rows: drop `xpay-publisher-sites-dev` row by `site_id`.
