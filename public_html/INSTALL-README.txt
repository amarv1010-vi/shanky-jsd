JSD CONSTRUCTION WEBSITE — QUICK INSTALL (10 minutes)
======================================================

You have already done the hard part: this zip is extracted.
Now just two steps:

STEP 1 — Create the database (cPanel)
  - cPanel > MySQL(R) Databases
  - Create Database  (e.g. ..._jsd)
  - Create User      (e.g. ..._jsdadmin) with a strong password
  - "Add User To Database" -> tick ALL PRIVILEGES
  - Write down: database name, user, password

STEP 2 — Run the installer (browser)
  - Visit  https://www.jsdconstruction.com.au/install.php
  - Enter the database details from Step 1
  - Choose your admin panel username + password
  - Enter the Titan password for info@jsdconstruction.com.au
    (or leave blank and add it later in api/config.php)
  - Click "Install Website" — done!

The installer creates all tables with starter content, writes the
config file, then deletes itself automatically.

AFTER INSTALL
  - Website:      https://www.jsdconstruction.com.au
  - Admin panel:  https://www.jsdconstruction.com.au/admin/
    (enquiries database, photo uploads with automatic 3:2 crop,
     testimonials)
  - You can delete this INSTALL-README.txt file.

Full documentation: https://github.com/amarv1010-vi/shanky-jsd
(README.md, docs/DEPLOYMENT.md, docs/ASSETS-GUIDE.md)
