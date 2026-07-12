# Adding Real Project Photos — The Complete Guide

This is the **one-stop, drop-a-folder** system for putting JSD's real project
photos on the website with guaranteed perfect alignment. No cryptic renaming,
no per-photo admin clicking, no coding.

## Why alignment is automatic (the thing other tools got wrong)

Every photo slot on the site is locked to a **3:2 frame** with CSS
`object-fit: cover`. That means *any* photo — portrait, square, ultra-wide —
is automatically centre-cropped into an identical, pixel-aligned card.
ChatGPT/Grok looked broken because they inserted raw images without this frame.
Here it is enforced site-wide and **verified**: test images of 2000×3500,
5000×2000, 3000×3000 and 4000×3000 all render at exactly 1.5 ratio.

## The folder structure

Photos live in `public_html/uploads/gallery/`, in these category subfolders
(already created, empty, waiting for photos):

```
uploads/gallery/
├── high-set/       → High-Set Houses
├── low-set/        → Low-Set Houses
├── split-level/    → Split-Level Homes
├── commercial/     → Low-Rise Commercial
└── featured/       → Best hero shots (lead the home page)
```

`api/gallery.php` scans these folders on every page load — **no database, no
config, no build step**. Drop files in, they appear.

## Naming (controls order + caption)

`NN Title Of Project.jpg`
- Leading number → display order (`01` first).
- The rest → the caption shown under the photo.
- Dashes/underscores become spaces. A missing number is fine (those sort last).

Examples:
```
high-set/01 Rochedale Family Home.jpg
high-set/02 Calamvale Hillside Build.jpg
low-set/01 Springwood Open-Plan Home.jpg
commercial/01 Underwood Retail Development.jpg
featured/01 Signature Rochedale Build.jpg
```

## Photoshop / sizing

**Easiest (recommended):** don't crop. Export landscape JPEGs ≥ 1600 px wide,
quality 80. The site crops to 3:2 and centres automatically.

**If you want exact control:** crop 3:2, export **1500 × 1000 px**, JPEG q80 —
that's the precise frame the site uses.

Landscape looks best; portrait/square still align (centre-cropped).

## Upload pipeline (~5 min, one time)

1. On your Mac, drop named photos into the category subfolders.
2. Select the `gallery` folder → right-click → **Compress**.
3. cPanel → **File Manager** → `public_html/uploads/` → **Upload** the zip.
4. Right-click the zip → **Extract** (overwrite when asked).
5. Open `https://www.jsdconstruction.com.au/projects.html` — live, cropped,
   aligned, with a click-to-zoom lightbox.

To add more later: drop more files in and re-upload. That's the whole loop.

## How the site chooses what to show

`main.js` loads photos in this priority:
1. **Gallery folder** (this system) — if it has any photos, they win outright.
2. **Admin/DB projects** (`admin/projects.php` uploads) — if the gallery is empty.
3. **Built-in placeholders** — if neither exists (so a fresh site never looks broken).

So the moment real photos land in the gallery, the demo placeholders disappear.

## Two ways to manage photos (pick either)

- **Folder drop (this guide):** best for bulk / one-shot. Fastest.
- **Admin panel** (`/admin/` → Projects & Photos): upload per-project in the
  browser; server auto-crops to 3:2. Best for adding one project at a time.

Both feed the same 3:2 gallery + lightbox. Use whichever suits the moment.
