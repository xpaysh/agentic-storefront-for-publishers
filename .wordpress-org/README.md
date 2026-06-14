# WP.org page assets (NOT shipped in the plugin zip)

These are the marketing assets for the wordpress.org/plugins listing — they
live in the SVN `assets/` directory, **not** in plugin `trunk/`, so they do
not bloat the installable zip.

| File | Purpose |
|---|---|
| `icon-256x256.png` / `icon-128x128.png` | Plugin directory icon (xpay✦ brand mark, copied verbatim from the `xpay-woocommerce` sibling — same family mark) |
| `banner-772x250.png` / `banner-1544x500.png` (retina) | Listing header banner |

## Banner provenance

The banner is generated deterministically by `gen-banner.py` so the text is
crisp and correct (image models garble small text). It matches the
`xpay-woocommerce` sibling banner: brand green gradient (#00C48C→#4AF0A8),
`{xpay✦}` wordmark top-left, Georgia serif/italic headline, xpay-settlement
subline. Copy is Publishers-specific ("Turn your Content into Commerce").

To regenerate: `python3 gen-banner.py` (needs Pillow + macOS system fonts),
then copy the two banner PNGs into the SVN `assets/` checkout and
`svn ci assets`.

## Deploying to WP.org

The SVN checkout lives at `~/wporg/xpay-agentic-commerce-for-publishers/`.
Assets go in its `assets/` dir; commit with
`svn ci assets -m "..." --username xpaysh`. No version bump or re-review
needed for asset-only changes.
