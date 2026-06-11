# InstaWP test plan — v0.4.0 (post-rename, pre-WP.org-resubmit)

This pass exercises every change from the WP.org review reply: slug rename, native settings page, HMAC-signed `/page-context`, removed auto-injection, prefix sweep, and nonce-gated admin actions.

Plan on ~30 minutes for the full pass.

---

## 0. Build the zip

```bash
cd xpay-agentic-commerce-for-publishers
# Update SLUG inside build-zip.sh first (was agentic-storefront-for-publishers).
sed -i '' 's/SLUG="agentic-storefront-for-publishers"/SLUG="xpay-agentic-commerce-for-publishers"/' scripts/build-zip.sh
bash scripts/build-zip.sh
# → build/xpay-agentic-commerce-for-publishers.zip
```

Sanity-check the zip:

```bash
unzip -l build/xpay-agentic-commerce-for-publishers.zip | head -25
# Expect: xpay-agentic-commerce-for-publishers/<files>, no asp-*, no auto-inject
```

---

## 1. Fresh InstaWP install

1. Spin up a fresh WordPress site on InstaWP. Pick PHP 8.1+ and WP 6.7+ for parity with the WP.org reviewer's environment.
2. **Plugins → Add New → Upload Plugin** → upload `xpay-agentic-commerce-for-publishers.zip`. Activate.
3. Confirm in the plugin list:
   - **Name** reads "xpay✦ Agentic Commerce for Publishers" (with the ✦ character, not `?` or `□`).
   - No "Storefront" anywhere in the row.
4. Add the dev backend override to `wp-config.php` (above the "stop editing" line):

   ```php
   define( 'XPAYACP_API_BASE_OVERRIDE', 'https://1iiczxdfea.execute-api.us-east-1.amazonaws.com' );
   ```

5. Activation should auto-redirect once to **Settings → xpay Agentic Commerce**.

**Pass:** plugin name shows the new label; menu item appears under Settings; no PHP notices on the Plugins page.

---

## 2. Native settings page renders

Open **Settings → xpay Agentic Commerce**. Confirm the page is a standard wp-admin form — no iframe.

```bash
# In the page source (View Source), this should return ZERO matches:
grep -c "widget.xpay.sh/embed/admin" page-source.html
# → 0
```

Visual checks:
- "Connection" card at top with a **Connect site** button.
- "Agent discovery" section with two checkboxes.
- "Recommendation widget" section with Amazon tag input, two textareas, one checkbox.
- "External services" disclosure card at the bottom listing `publisher-api.xpay.sh`, `widget.xpay.sh`, `app.xpay.sh`.
- Terms + Privacy links point at `xpay.sh/legal/terms-of-use/` and `xpay.sh/legal/privacy-policy/`.

**Pass:** No iframe. All form fields are native WP `.form-table` rows. No console errors.

---

## 3. Settings save round-trip

1. Fill in:
   - Agent storefront endpoint: **checked**
   - Augment /llms.txt: **unchecked**
   - Amazon Associates tag: `myblog-20`
   - Excluded categories: `alcohol, weapons`
   - Excluded merchant domains: `competitor.com, brand-x.com`
   - Personalization: **unchecked**
2. Click **Save Changes**. Expect the WP "Settings saved" notice.
3. Refresh the page — all values should persist.
4. View source on the form — confirm the nonce field is present:

   ```html
   <input type="hidden" id="_wpnonce" name="_wpnonce" value="…" />
   ```

5. Try a malformed Amazon tag (e.g. `BAD_TAG`) and save — should silently empty the field (sanitiser drops invalid format).

**Pass:** All seven fields persist; bad Amazon tag is rejected; nonce field exists.

---

## 4. Connect flow (capture-return)

Mint a `site_id` via the dev backend:

```bash
curl -sS -X POST https://1iiczxdfea.execute-api.us-east-1.amazonaws.com/storefront/register \
  -H 'Content-Type: application/json' \
  -d "{\"site_url\":\"https://<your-instawp>.instawp.xyz\",\"admin_email\":\"sri@xpay.sh\"}"
# → {"site_id":"site_...","ok":true}
```

Or use the pre-minted `site_773571663617412c91` if reusing the previous test host.

Hit the capture-return URL while logged into wp-admin:

```
https://<your-instawp>.instawp.xyz/wp-admin/options-general.php?page=xpay-agentic-commerce-for-publishers&xpayacp_site_id=<id>&xpayacp_connected=1
```

**Pass:** Page redirects to itself with `?xpayacp_just_connected=1`, success notice appears, the Connection card now reads "Connected. Site ID: `site_…`" with Open Dashboard + Disconnect buttons.

**Negative tests** (do these in a private window logged-OUT):

```
https://<host>/wp-admin/options-general.php?page=xpay-agentic-commerce-for-publishers&xpayacp_site_id=BAD&xpayacp_connected=1
```

**Pass:** Redirected to wp-login (capability check blocks unauthenticated).

Then logged back in, try a malformed id:

```
?xpayacp_site_id=$$$&xpayacp_connected=1
```

**Pass:** No update; site_id not overwritten (regex rejects).

---

## 5. Disconnect (nonced action)

Click **Disconnect** on the Connection card. Confirm browser prompt. Expect:
- Redirect to settings with `xpayacp_disconnected=1` and info notice.
- Connection card reverts to "Connect site" button.
- `wp_options.xpayacp_site_id` is now absent.

**Negative test:** Try to disconnect without the nonce via `curl`:

```bash
curl -i -X POST https://<host>/wp-admin/admin-post.php \
  -d "action=xpayacp_disconnect" \
  --cookie "wordpress_logged_in_…=…"
# → 403 / "are you sure" error page
```

**Pass:** No nonce = no disconnect.

---

## 6. Auto-injection removed (regression check)

Reconnect (repeat step 4).

Create a new post titled "Instant Pot Biryani Recipe". Body: just one paragraph of prose, **no shortcode, no block**. Publish. Visit the post on the front-end.

**Pass:** No recommendation widget below the content. View source — confirm no `<iframe ... widget.xpay.sh/embed/recs/`.

This is the single biggest regression risk vs 0.3.x. If a widget appears here, the auto-inject removal failed.

---

## 7. Shortcode renders + carries signed token

Edit the post, append `[xpay_recs]` at the end. Update. Visit the post.

**Pass:** Iframe renders. View source, find the iframe `src`. It should contain:

```
widget.xpay.sh/embed/recs/inline?site_id=…&layout=…&limit=…&context=…&origin=…&post_id=N&ctx_ts=<unix>&ctx_sig=<64-hex>
```

Confirm:
- `post_id` matches the post.
- `ctx_ts` is a recent unix timestamp (within seconds of now).
- `ctx_sig` is 64 hex chars.

---

## 8. Gutenberg block

Edit the post, remove the shortcode, insert the **Recommendations** block (search "xpay" in the inserter; block name `xpay/recommendations`). Set heading "Recommended", limit 3, layout "cards". Update.

**Pass:** Front-end renders an iframe identical to the shortcode case, with the same signed-token query params.

---

## 9. `/page-context` HMAC gate

From the rendered iframe URL above, copy `post_id`, `ctx_ts`, `ctx_sig`.

**Valid request:**

```bash
curl -sS "https://<host>/wp-json/xpayacp/v1/page-context?post_id=<id>&ts=<ctx_ts>&sig=<ctx_sig>"
# → 200 { "post_id":…, "url":…, "title":…, "categories":[…], "tags":[…], "lang":… }
```

**Negative tests** — each must return `401`/`403`:

```bash
# 1. Missing sig
curl -i "https://<host>/wp-json/xpayacp/v1/page-context?post_id=<id>&ts=<ctx_ts>"
# → 401

# 2. Wrong sig
curl -i "https://<host>/wp-json/xpayacp/v1/page-context?post_id=<id>&ts=<ctx_ts>&sig=deadbeef"
# → 401

# 3. Stale ts (24h ago)
STALE=$(( $(date +%s) - 86400 ))
curl -i "https://<host>/wp-json/xpayacp/v1/page-context?post_id=<id>&ts=$STALE&sig=<ctx_sig>"
# → 401

# 4. Future ts (1 day ahead)
FUTURE=$(( $(date +%s) + 86400 ))
curl -i "https://<host>/wp-json/xpayacp/v1/page-context?post_id=<id>&ts=$FUTURE&sig=<ctx_sig>"
# → 401

# 5. Different post id with someone else's sig
curl -i "https://<host>/wp-json/xpayacp/v1/page-context?post_id=999&ts=<ctx_ts>&sig=<ctx_sig>"
# → 401

# 6. Valid sig but post is a draft (admin: flip the post to draft, get a fresh sig)
# Expect: 404 (not 401 — sig still valid)
```

**Pass:** All six negative cases blocked; valid request returns the public metadata.

---

## 10. Agent storefront endpoint

```bash
curl -i https://<host>/.well-known/agent-storefront.json
```

**Pass:** `200`, `Content-Type: application/json`, `X-ASP-Emitter: asp` (wire-level identifier intentionally preserved), `Cache-Control: public, max-age=900`, body is JSON with the catalog products.

Toggle "Agent storefront endpoint" off in settings. Repeat.

**Pass:** `404`.

Re-enable. Test the detect-not-clobber: create a real file at `/.well-known/agent-storefront.json` on the host (use InstaWP's file manager to drop one in the webroot). Run the probe-cache reset by saving settings again. Re-curl.

**Pass:** Plugin refuses to serve (returns `404`); some other emitter wins. This protects publishers who already have an `agent-storefront.json` from being silently clobbered.

---

## 11. `/llms.txt`

```bash
curl -i https://<host>/llms.txt
# → 404 (augment is off by default)
```

Enable "Augment /llms.txt" in settings. Save. Re-curl.

**Pass:** `200`, `Content-Type: text/plain`, body contains the `# <site name>` header, `Site:` line, and the fenced `<!-- xpay:agent-storefront:begin --> … <!-- xpay:agent-storefront:end -->` block.

Repeat the detect-not-clobber test by dropping a real `llms.txt` in the webroot, save settings to flush the probe cache, re-curl.

**Pass:** Plugin refuses to overwrite.

---

## 12. `wp plugin-check` (run locally before submitting)

```bash
# On a local WP install with the WP-CLI plugin-check addon:
wp plugin install plugin-check --activate
wp plugin install xpay-agentic-commerce-for-publishers --activate
wp plugin check xpay-agentic-commerce-for-publishers
```

**Pass:** Zero ERROR-level findings. WARNINGs reviewed individually (most common ones are about i18n / late escaping; the codebase has been audited).

Or do this through the WP admin UI: **Tools → Plugin Check → run on this plugin**.

---

## 13. Uninstall cleanup

In wp-admin: **Plugins → Deactivate**, then **Delete**.

After deletion, query `wp_options`:

```sql
SELECT option_name FROM wp_options WHERE option_name LIKE 'xpayacp_%';
-- Expect: zero rows.
```

Same for transients:

```sql
SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_xpayacp_%';
-- Expect: zero rows.
```

**Pass:** Clean wipe.

---

## 14. Static scans (before zipping)

```bash
cd xpay-agentic-commerce-for-publishers

# No leftover prefix
grep -rn '\bASP_\|'"'"'asp_\|asp/v1\|auto-inject\|Auto_Inject\|settings-bridge' \
  --include="*.php" --include="*.js" --include="*.json"
# → empty

# No widget.xpay.sh admin iframe
grep -rn "embed/admin/settings" --include="*.php"
# → empty

# All PHP files lint clean
php -l xpay-agentic-commerce-for-publishers.php uninstall.php includes/*.php
```

---

## 15. Reviewer-checklist final sweep

Before replying to plugins@wordpress.org, eyeball the readme one more time and confirm:

- [ ] `=== xpay✦ Agentic Commerce for Publishers ===` (no "Storefront").
- [ ] `Stable tag: 0.4.0`.
- [ ] No mentions of the embedded `widget.xpay.sh/embed/admin/settings` iframe.
- [ ] No mentions of auto-injection.
- [ ] Description's "what it does" list matches the installation steps (no contradiction between sections).
- [ ] All Terms / Privacy URLs are `xpay.sh/legal/terms-of-use/` + `xpay.sh/legal/privacy-policy/` with trailing slashes.

---

## Backend coordinates (unchanged from v0.3.x)

- Dev base: `https://1iiczxdfea.execute-api.us-east-1.amazonaws.com`
- Account: `582760238698` (us-east-1), profile `agentically`
- Pre-minted test site for `snowflake-quoll-134a4c.instawp.site`: `site_773571663617412c91`
- DDB table: `xpay-publisher-sites-dev` (drop by `site_id` to teardown).

## What's NOT in this build (vs the rejection notes)

- The reviewer's AI suggested name "xpay Product Recommendations for Publishers" — we picked the more on-brand "xpay✦ Agentic Commerce for Publishers" instead. If WP.org pushes back on the name a second time, the slug `xpay-recommendations-for-publishers` is the fallback.
- The ✦ glyph appears only in the **display name**, never in the slug. If a reviewer flags the unicode (extremely unlikely — `xpay✦ Agentic Commerce for WooCommerce` is already approved), drop to ASCII "xpay Agentic Commerce for Publishers" with no other changes.
