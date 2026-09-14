# SEO Phase 1 — technical fixes (Day 1)

Deployment package: `dist/jsd-seo-phase1.zip` (104 KB, 13 files).

This package answers the technical audit points raised by the client's SEO
agency. It is a **surgical overlay**: it replaces only the files listed below.
It does not contain — and therefore cannot touch — the database, the admin
panel, the customer account area, the API, `api/config.php`, or any uploaded
photo or video.

## How to deploy (cPanel File Manager)

1. File Manager → `public_html` → select `index.html` and the `assets` folder →
   **Compress** → keep the resulting `.zip` as a rollback copy.
2. Upload `jsd-seo-phase1.zip` into `public_html`.
3. Right-click it → **Extract** → extract into `public_html` → **Overwrite** when
   asked.
4. Delete `jsd-seo-phase1.zip` from the server.
5. Hard-refresh the site (Ctrl/Cmd + Shift + R).

Rollback: extract the backup zip from step 1 over the top.

## What is in the package

| File | Change |
| --- | --- |
| `robots.txt` | **New.** Allows crawling, blocks `/admin/`, `/account/`, `/api/`, names the sitemap, and explicitly welcomes AI crawlers (GPTBot, ClaudeBot, PerplexityBot, etc.). |
| `sitemap.xml` | **New.** All 7 public pages with `lastmod`, `changefreq` and `priority`. The private login area is excluded. |
| `llms.txt` | **New.** Plain-text company summary for AI assistants. |
| `assets/img/og-image.jpg` | **New.** 1200×630 branded link-preview image. |
| `index.html` … `download.html` (7 pages) | Unique `<title>` + meta description per page; per-page `og:url`; `rel="canonical"`; full Open Graph + Twitter card block; `robots` and `theme-color` meta. |
| `index.html`, `contact.html` | `GeneralContractor` JSON-LD (name, phone, email, address, service areas, services, social profile). |
| `index.html` | H1 is now "Luxury Home Builder in Brisbane & South East Queensland". The four hero statistics carry their real values in the HTML source. |
| `assets/js/main.js` | Counters read their real value from the HTML and are reset to zero only for the visual count-up. Visitors who prefer reduced motion see the final number immediately. |
| `assets/css/style.css` | Three scoped rules appended: hero H1 sizing, the H1 region sub-line, and a "other mailboxes" label on the contact page. Nothing existing was edited. |
| `contact.html` | The four alias mailboxes are grouped under an "Other mailboxes" label so `contact@` reads as the single primary address. |

## Audit points and status

| # | Point | Status |
| --- | --- | --- |
| 1 | robots.txt missing | Fixed |
| 2 | sitemap.xml missing / stale | Fixed — all 7 live pages, no dead URLs |
| 3 | Homepage H1 not keyword-rich | Fixed |
| 4 | Stats rendering "0" to crawlers | Fixed — real numbers in the HTML source |
| 5 | `og:url` wrong on Contact and Projects | Fixed on all 7 pages, not just the two reported |
| 6 | Meta tags possibly JS-injected | Already server-side; verified in view-source on all 7 pages. Also added canonical, robots and Twitter tags |
| 7 | LocalBusiness schema missing | Added as `GeneralContractor` (a LocalBusiness subtype, more specific and therefore stronger) |
| 8 | Testimonials not in view-source | Phase 2 |
| 9 | Contact page emails equally weighted | Fixed — `contact@` is primary, the rest labelled "Other mailboxes" |
| 10 | GA4 tag | Phase 3 — needs the Measurement ID |

## Verification performed before release

- All 7 pages rendered in headless Chromium at 1440, 1280, 1024, 768, 390 and
  360 px with the production web fonts: **zero JavaScript console errors**.
- Hero H1 holds two display lines at every width tested (a narrow-phone rule was
  added so 320–389 px behaves exactly as the old, shorter headline did).
- Stat counters animate and settle on 120+, 14+, 4, 100%.
- JSON-LD parses as valid JSON on both pages; `sitemap.xml` parses as valid XML.
- `view-source` confirmed to contain title, description, robots, canonical,
  `og:url` and the real stat numbers on every page.
- The package was extracted over a pristine copy of the live site: every file
  matched the intended result byte for byte, and a stand-in `api/config.php`,
  gallery photo and video were left untouched.

## Known, pre-existing (not introduced here)

At viewport widths of 360 px and below, `contact.html` scrolls about 5 px
horizontally. This is present in the current live site as well — confirmed by
measuring the pre-change build — and is queued for Phase 2.

## Still to come

- **Phase 2** — server-render the project gallery, featured photos and
  testimonials into the initial HTML (point 8), plus the 360 px contact fix.
- **Phase 3** — GA4 tag once the Measurement ID arrives (point 10).
