#!/usr/bin/env python3
"""Generate the wordpress.org listing banner for xpay Agentic Commerce for
Publishers. Matches the xpay-woocommerce sibling banner style. Deterministic
text render (image models garble small text). Requires Pillow + macOS fonts.

Output: banner-1544x500.png (2x) + banner-772x250.png, written next to this file.
Then copy both into the SVN assets/ checkout and `svn ci assets`.
"""
import math
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

HERE = Path(__file__).resolve().parent
# logo wordmark source (navy {xpay} mark on transparent)
LOGO = HERE.parents[1] / "xpay-website/public/logo/logo-full.png"

S = 2                       # supersample
W, H = 772 * S, 250 * S
NAVY = (14, 26, 64)
LEFT, RIGHT = (0, 196, 140), (74, 240, 168)

img = Image.new("RGB", (W, H))
px = img.load()
for x in range(W):
    t = x / (W - 1)
    col = tuple(int(LEFT[i] + (RIGHT[i] - LEFT[i]) * t) for i in range(3))
    for y in range(H):
        px[x, y] = col

# subtle vertical light streaks
streak = Image.new("L", (W, H), 0)
sd = ImageDraw.Draw(streak)
for x in range(W):
    sd.line([(x, 0), (x, H)], fill=int(18 * (0.5 + 0.5 * math.sin(x / 26.0))))
img = Image.composite(Image.new("RGB", (W, H), (180, 255, 210)), img, streak)

draw = ImageDraw.Draw(img)

logo = Image.open(LOGO).convert("RGBA")
lh = 64 * S
logo = logo.resize((int(logo.width * lh / logo.height), lh), Image.LANCZOS)
img.paste(logo, (40 * S, 26 * S), logo)

F = "/System/Library/Fonts/Supplemental/"
def font(name, size): return ImageFont.truetype(F + name, size * S)
f_h, f_hi = font("Georgia.ttf", 27), font("Georgia Bold Italic.ttf", 27)
f_sub = font("Arial.ttf", 13)

def line(x, y, parts):
    for text, fnt in parts:
        draw.text((x, y), text, font=fnt, fill=NAVY)
        x += draw.textlength(text, font=fnt)

x0 = 42 * S
line(x0, 112 * S, [("Shopping is happening on ", f_h), ("AI Chat", f_hi)])
line(x0, 150 * S, [("Turn your ", f_h), ("Content into Commerce", f_hi)])
draw.text((x0 + 2 * S, 196 * S),
          "Contextual product recommendations for your WordPress site — "
          "settle on your own gateway with xpay.", font=f_sub, fill=(18, 54, 44))

img.save(HERE / "banner-1544x500.png")
img.resize((772, 250), Image.LANCZOS).save(HERE / "banner-772x250.png")
print("wrote banner-1544x500.png + banner-772x250.png")
