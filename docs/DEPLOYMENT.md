# Deployment Guide — GoDaddy cPanel (Web Hosting Plus)

Go-live checklist for **www.jsdconstruction.com.au**. Total time ≈ 30 minutes.

## Step 1 — Upload the site

1. Log in to GoDaddy → your hosting → **cPanel Admin** → **File Manager**.
2. Open `public_html` (delete any default placeholder files).
3. Upload the **contents of this repo's `public_html/` folder** (zip it locally, upload the zip,
   right-click → Extract, then move the extracted contents so `index.html` sits directly in
   `public_html`).
4. Confirm the `.htaccess` file uploaded (enable "Show Hidden Files" in File Manager settings).

## Step 2 — Create the MySQL database

1. cPanel → **MySQL® Databases**.
2. Create database (e.g. `jsdcon_website`) and a user (e.g. `jsdcon_admin`) with a strong password.
3. **Add User To Database** → tick **ALL PRIVILEGES**.
4. cPanel → **phpMyAdmin** → select the database → **Import** → choose `database/schema.sql`
   from this repo → Go. This creates all tables **and seeds 6 sample projects + 3 testimonials**.

## Step 3 — Configure the backend

1. In File Manager, go to `public_html/api/`.
2. Copy `config.example.php` → rename the copy to `config.php`.
3. Edit `config.php`:
   - `db` → the database name / user / password from Step 2 (note: cPanel prefixes both with your account name).
   - `admin` → choose a username; generate the password hash in cPanel → **Terminal**:
     `php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT);"` and paste the output.
   - `smtp` → password of the `info@jsdconstruction.com.au` Titan mailbox
     (host `smtp.titan.email`, port `465`, `ssl` are already correct for Titan).
4. Check PHP version: cPanel → **Select PHP Version** → PHP **8.1+** and make sure the
   **gd**, **pdo_mysql**, **curl** and **openssl** extensions are enabled (they are by default).

## Step 4 — SSL

cPanel → **SSL/TLS Status** → run **AutoSSL** for jsdconstruction.com.au and www.
The `.htaccess` already forces HTTPS + www.

## Step 5 — Verify (5-minute smoke test)

| Check | How |
|---|---|
| Home page + animations | visit https://www.jsdconstruction.com.au |
| Enquiry flow | submit the Enquiry form → confirm success message, thank-you email arrives, row appears in `/admin/enquiries.php` |
| Contact flow | send a contact message → arrives at contact@jsdconstruction.com.au |
| Estimator | change values → range updates live; submit → email + lead in admin |
| Admin login | https://www.jsdconstruction.com.au/admin/ |
| Photo upload | Admin → Projects & Photos → upload a phone photo → appears cropped 3:2 on `/projects.html` |
| WhatsApp button | tap the floating green button → opens chat with +61 424 475 767 |
| Instagram icon | footer icon → opens instagram.com/jsd.construction |

## Step 6 — (Later) enable WhatsApp automated replies

The site works without this; do it when ready:

1. Go to https://developers.facebook.com → create app → add the **WhatsApp** product.
2. In WhatsApp → API Setup, register **+61 424 475 767** as the business number
   (this migrates the number to the Cloud API — plan around existing WhatsApp usage).
3. Create a message **template** named `enquiry_received` (language `en_AU`), body e.g.:
   *"Hi {{1}}, thanks for contacting JSD Construction! We've received your enquiry and will
   be in touch within 1 business day. — Luxury Built in Australia"* → submit for approval.
4. Create a **System User** token (permanent) with `whatsapp_business_messaging` permission.
5. In `api/config.php` set `whatsapp.enabled = true`, paste `phone_number_id` and `access_token`.

## Updating the site later

- **Photos/projects/testimonials/enquiries** — all through `/admin/`, no code changes.
- **Code changes** — edit in this GitHub repo, then re-upload the changed files via File Manager
  (or set up cPanel **Git™ Version Control** to pull from GitHub for one-click deploys).
