# Asset Storage & Numbering Guide

Where to store JSD photos, logos and icons — and how to name them so any developer
**or LLM tool** can wire them into the website without guesswork.

## Where assets live

| Asset type | Location | In git? |
|---|---|---|
| Logos & favicon | `public_html/assets/img/logos/` | ✅ yes |
| UI icons (SVG) | inline in HTML/CSS (no files needed) | ✅ yes |
| Placeholder/brand artwork | `public_html/assets/img/` | ✅ yes |
| **Project photos (real)** | uploaded via **Admin → Projects & Photos** → stored server-side in `public_html/uploads/projects/` | ❌ no (server only) |
| Original/master files (RAW, PSD, full-res) | Google Drive folder `JSD-Website-Assets/` (recommended) — mirror the same numbering | ❌ no |

**Recommended master archive structure (Google Drive or similar):**
```
JSD-Website-Assets/
├── 01-logos/        logo-01-primary.svg, logo-02-white.png, logo-03-mark-only.png …
├── 02-projects/     proj-highset-01-01.jpg, proj-highset-01-02.jpg …  (originals, any size)
├── 03-team/         team-01-jagdeep.jpg …
├── 04-videos/       vid-archive-01.mp4 …  (for the future Archive tab)
└── 05-icons/        icon-01-whatsapp.svg …
```

## Numbering system

### Logos — `logo-<NN>-<variant>.<ext>`
- `logo-01-primary.svg` — full gold logo on dark (in use, header/footer)
- `logo-02-white.*` — reserve for white-on-photo version
- `logo-03-mark-only.*` — reserve for square social avatar
- `favicon.svg` — browser tab icon (in use)

### Project photos — `proj-<category>-<NN>-<SS>.<ext>` (manual/placeholder)
- `<category>` ∈ `highset | lowset | split | commercial`
- `<NN>` = project number within its category (01, 02, …)
- `<SS>` = photo sequence within the project (01 = cover)
- Current placeholders: `proj-highset-01-01.svg` … `proj-commercial-04-02.svg`

### Admin-uploaded photos (automatic — do not rename)
The admin panel names files itself: `proj-<projectId 6 digits>-<seq 2 digits>.jpg`
e.g. `proj-000012-03.jpg` = 3rd photo of project #12. Every upload is auto-cropped
to **3:2 (1500×1000 JPEG)**, so originals can be any size/orientation.

## Rules

1. **Never** upload real project photos into `assets/img/projects/` — always use the admin panel
   so they're cropped, numbered and registered in the database.
2. Photos should be landscape or roughly square for best 3:2 crops (the crop is centred).
3. Keep originals in the master archive at full resolution; the website only ever needs the
   admin-processed copies.
4. When the real logo files are ready, drop them into `public_html/assets/img/logos/` using the
   numbering above, then update the two `<img src="assets/img/logos/logo-01-primary.svg">`
   references (header + footer) if the filename differs.
