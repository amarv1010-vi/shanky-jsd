# JSD Construction Pty Ltd — Website (shanky-jsd)

> **Purpose of this README:** written so that any human developer **or LLM/AI tool** can fully
> understand what was built, which technologies were used, how every function works, and how to
> deploy or extend it. Read this file first; then see `docs/DEPLOYMENT.md` and `docs/ASSETS-GUIDE.md`.

---

## 1. What this is

A complete, production-ready website for **JSD Construction Pty Ltd** — a Brisbane (QLD, Australia)
residential & commercial builder ("Luxury Built in Australia").

- **Live domain (target):** https://www.jsdconstruction.com.au
- **Hosting (target):** GoDaddy Web Hosting Plus (cPanel: File Manager, PHP, MySQL/phpMyAdmin, AutoSSL)
- **Business phone / WhatsApp:** +61 424 475 767
- **Instagram:** https://www.instagram.com/jsd.construction/
- **Mailboxes (Titan Email):** contact@ / info@ / projects@ / quotes@ / support@ jsdconstruction.com.au

## 2. Technology choices (and WHY)

| Layer | Technology | Why |
|---|---|---|
| Frontend | Static HTML5 + custom CSS design system + vanilla JS | Zero build step — uploads straight to cPanel `public_html`. No Node/webpack needed on GoDaddy. |
| Animations | **GSAP 3 + ScrollTrigger** (CDN) | Scroll reveals, hero entrance, staggered cards. Degrades gracefully if CDN blocked. |
| Fonts | Google Fonts: **Marcellus** (display serif) + **Manrope** (body) | Luxury architectural look. |
| Backend | **Plain PHP 8** (no framework, no Composer) | GoDaddy cPanel runs PHP natively; nothing to install. |
| Database | **MySQL** via PDO prepared statements | Provided by cPanel; schema in `database/schema.sql`. |
| Email | Custom dependency-free **SMTP client** (`api/lib/Mailer.php`) talking to **Titan Email** (`smtp.titan.email:465` SSL) | Automated replies + internal notifications without PHPMailer/Composer. |
| WhatsApp | **Meta WhatsApp Business Cloud API** client (`api/lib/WhatsApp.php`) | Automated "thank you" WhatsApp messages. Disabled until API credentials exist (see §6). |
| Image processing | PHP **GD** (bundled on GoDaddy) | Server-side EXIF auto-rotate + centre-crop to **3:2** (1500×1000 JPEG) on every admin upload. |

**Brand palette** (sampled from the metallic champagne-gold logo):
`#CFA168` / `#C1955D` primary · `#E0B171` highlight · `#9F7745` / `#7B613A` bronze shadows —
defined as CSS variables in `public_html/assets/css/style.css` (`:root`).

## 3. Repository layout

```
public_html/              ← upload THIS folder's contents to cPanel public_html
├── index.html            Home: hero, stats, services, featured projects, testimonials, areas, CTA
├── projects.html         Filterable project gallery (API-driven, 3:2 image frames)
├── estimator.html        EXTRA USE-CASE #1: instant build-cost estimator + lead capture
├── enquiry.html          Enquiry tab (Name, Mobile, Email, Purpose, Area, alt. contact, message)
├── contact.html          Contact tab (routes email to contact@/quotes@/projects@/support@)
├── archive.html          Archive tab — "Coming Soon" page
├── .htaccess             HTTPS+www redirect, config protection, caching, hardening
├── assets/
│   ├── css/style.css     Entire design system (champagne-gold on dark charcoal)
│   ├── js/main.js        Nav, GSAP reveals, counters, gallery, AJAX forms, estimator maths
│   └── img/              Logos, hero art, placeholder project SVGs (see docs/ASSETS-GUIDE.md)
├── api/                  JSON endpoints (all return {ok: bool, ...})
│   ├── config.example.php  ← copy to config.php on the server & fill credentials (gitignored)
│   ├── bootstrap.php     Config+PDO+helpers loaded by every endpoint
│   ├── enquiry.php       POST: store enquiry → email customer + notify JSD + WhatsApp
│   ├── contact.php       POST: store message → forward to chosen mailbox + acknowledge customer
│   ├── estimate.php      POST: store estimator lead → email estimate + notify quotes@
│   ├── projects.php      GET: published projects (feeds gallery)
│   ├── testimonials.php  GET: published testimonials (feeds home page)
│   └── lib/              Mailer.php (Titan SMTP) + WhatsApp.php (Meta Cloud API)
├── admin/                Password-protected admin panel (PHP sessions + CSRF)
│   ├── index.php         Login
│   ├── dashboard.php     Counts + latest enquiries
│   ├── enquiries.php     FULL DATABASE TABLES (enquiries/contacts/estimator leads) + CSV export + status workflow
│   ├── projects.php      Create projects, upload photos (auto 3:2 crop), publish/hide/delete
│   ├── testimonials.php  EXTRA USE-CASE #2: manage client reviews shown on home page
│   └── auth.php/style.css/partials/layout.php
└── uploads/projects/     Admin-uploaded photos land here as proj-<id>-<seq>.jpg (gitignored)
database/schema.sql       Import once via phpMyAdmin (includes seed projects + testimonials)
docs/DEPLOYMENT.md        Step-by-step GoDaddy cPanel go-live guide
docs/ASSETS-GUIDE.md      Photo/logo numbering system & where to store originals
```

## 4. Feature map (requirement → implementation)

| # | Requirement | Where implemented |
|---|---|---|
| 1 | Enquiry tab with short form → database → admin table | `enquiry.html` → `api/enquiry.php` → `enquiries` table → `admin/enquiries.php` |
| 1a | Automated thank-you **email** to customer | `api/enquiry.php` + `api/lib/Mailer.php` (Titan SMTP — **yes, SMTP setup needed**, see §6) |
| 1b | Automated **WhatsApp** reply | `api/lib/WhatsApp.php` (Meta Cloud API; enable in `config.php` once approved, see §6) |
| 2 | WhatsApp button → wa.me/+61424475767 | Floating button on every page (`.wa-float`) + footer + contact page |
| 3 | Instagram icon → instagram.com/jsd.construction | Footer + contact page social icons |
| 4 | Archive tab (coming soon) | `archive.html` |
| 5 | Admin photo upload → Projects panel, **3:2 cropped** | `admin/projects.php` (`crop_to_ratio()` — GD centre-crop 1500×1000, EXIF rotate) |
| 6 | Two extra use-cases | **Cost Estimator** (`estimator.html` + `api/estimate.php`) and **Testimonials manager** (`admin/testimonials.php` + `api/testimonials.php`) |
| 7 | Contact tab emailing contact@… behind a short form | `contact.html` → `api/contact.php` (topic routes to contact@/quotes@/projects@/support@) |

Also included: honeypot + per-IP rate limiting on all forms, CSRF protection in admin,
prepared statements everywhere, `.htaccess` hardening, mobile-first responsive layout,
`prefers-reduced-motion` support, SEO meta/OG tags.

## 5. Data flow (for any LLM continuing this work)

```
Customer browser (enquiry.html form)
   └─ JS main.js validates → POST JSON → api/enquiry.php
        ├─ INSERT INTO enquiries (MySQL)
        ├─ Mailer::send() → customer thank-you (branded HTML template)
        ├─ WhatsApp::sendTemplate() → customer (only if enabled in config.php)
        └─ Mailer::send() → contact@jsdconstruction.com.au (internal alert)
JSD staff → /admin/ (login) → enquiries.php (structured tables, CSV export, status workflow)
JSD staff → /admin/projects.php (upload photos → GD crops to 3:2 → uploads/projects/)
Public projects.html → GET api/projects.php → renders gallery (falls back to built-in
   placeholder list in main.js if the API/database is unreachable)
```

## 6. Things the owner must configure (one-time)

1. **`api/config.php`** — copy from `config.example.php`; set MySQL credentials, admin password hash
   (`php -r "echo password_hash('…', PASSWORD_DEFAULT);"`), Titan SMTP password.
2. **SMTP (Titan): YES, required for automated emails.** Nothing to change inside the Titan app —
   just use any active mailbox (recommended `info@jsdconstruction.com.au`) with
   host `smtp.titan.email`, port `465`, SSL, username = full email address, password = mailbox password.
3. **WhatsApp automation** needs the **Meta WhatsApp Business Cloud API** (a normal WhatsApp phone
   can't send automated replies): create a Meta developer app → add WhatsApp → register
   +61 424 475 767 → get an approved template `enquiry_received` → paste token + phone-number-ID
   into `config.php` and set `enabled => true`. Until then customers still get the email auto-reply
   (which contains a wa.me chat link), so nothing breaks.
4. **Database** — create in cPanel, import `database/schema.sql` in phpMyAdmin.

## 7. Local development

```bash
cd public_html && php -S localhost:8080     # pages + admin (needs a MySQL and api/config.php)
```
The frontend renders fully without PHP (project gallery/testimonials fall back to built-in data),
so pure design work needs only a static server.

## 8. Conventions for future contributors (human or LLM)

- Keep all colours as CSS variables; never hard-code hex values in components.
- All API responses: `{ "ok": true|false, ... }`; all writes via PDO prepared statements.
- New forms: add `data-endpoint="api/x.php"` and a honeypot input named `website`; `main.js` handles the rest.
- Project images must remain **3:2**; upload pipeline guarantees this — don't bypass it.
- Asset naming/numbering: follow `docs/ASSETS-GUIDE.md` strictly so images can be wired up programmatically.
