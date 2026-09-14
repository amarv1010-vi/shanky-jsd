<?php
/**
 * JSD Construction — configuration template.
 *
 * SETUP: copy this file to config.php (same folder) and fill in real values.
 * config.php is gitignored so credentials never reach GitHub.
 *
 *   cp config.example.php config.php
 *
 * On GoDaddy cPanel: create the MySQL database + user in "MySQL Databases",
 * import database/schema.sql via phpMyAdmin, then put the credentials below.
 */

return [

    // ---------- MySQL (create in cPanel > MySQL Databases) ----------
    'db' => [
        'host'     => 'localhost',
        'name'     => 'jsdcon_website',      // cPanel prefixes e.g. username_dbname
        'user'     => 'jsdcon_admin',
        'pass'     => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
    ],

    // ---------- Admin panel login ----------
    // Generate a new hash with:  php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT);"
    'admin' => [
        'username'      => 'jsdadmin',
        'password_hash' => '$2y$10$CHANGE_ME_RUN_password_hash',
        'session_name'  => 'jsd_admin_session',
    ],

    // ---------- Outgoing email via Titan SMTP ----------
    // YES — SMTP must be configured with Titan for automated replies.
    // Titan settings (fixed by Titan, same for every mailbox):
    //   host: smtp.titan.email   port: 465 (SSL)  — or 587 (STARTTLS)
    //   username: full mailbox address, password: that mailbox's password.
    // Recommended sender: a no-reply style alias, e.g. info@jsdconstruction.com.au.
    'smtp' => [
        'enabled'   => true,
        'host'      => 'smtp.titan.email',
        'port'      => 465,
        'secure'    => 'ssl',                              // 'ssl' (465) or 'tls' (587)
        'username'  => 'info@jsdconstruction.com.au',
        'password'  => 'CHANGE_ME_TITAN_MAILBOX_PASSWORD',
        'from'      => 'info@jsdconstruction.com.au',
        'from_name' => 'JSD Construction Pty Ltd',
    ],

    // Where internal notifications go when a customer submits a form.
    'notify' => [
        'enquiry'  => 'contact@jsdconstruction.com.au',
        'contact'  => 'contact@jsdconstruction.com.au',
        'quotes'   => 'quotes@jsdconstruction.com.au',
        'projects' => 'projects@jsdconstruction.com.au',
        'support'  => 'support@jsdconstruction.com.au',
        'info'     => 'info@jsdconstruction.com.au',
    ],

    // ---------- WhatsApp automated replies (Meta WhatsApp Cloud API) ----------
    // Automated OUTBOUND WhatsApp messages cannot be sent from a normal phone —
    // they require the WhatsApp Business Cloud API (free tier available):
    //   1. Create a Meta Business account + app at developers.facebook.com
    //   2. Add the "WhatsApp" product, register +61 424 475 767 as the business number
    //   3. Create a message template (e.g. "enquiry_received"), get it approved
    //   4. Paste the permanent access token + phone number ID below and set enabled=true
    // Until then the site still works — customers get the email auto-reply and
    // a wa.me chat link inside it.
    'whatsapp' => [
        'enabled'          => false,
        'phone_number_id'  => '',            // from Meta app dashboard
        'access_token'     => '',            // permanent system-user token
        'template_name'    => 'enquiry_received',
        'template_lang'    => 'en_AU',
    ],

    // ---------- Uploads ----------
    'uploads' => [
        'dir'          => __DIR__ . '/../uploads/projects', // web-served path: /uploads/projects
        'max_bytes'    => 10 * 1024 * 1024,                 // 10 MB per image
        'ratio_w'      => 3,                                // enforced 3:2 crop
        'ratio_h'      => 2,
        'out_width'    => 1500,                             // output size 1500x1000
    ],

    'site' => [
        'name'     => 'JSD Construction Pty Ltd',
        'url'      => 'https://www.jsdconstruction.com.au',
        'phone'    => '+61 424 475 767',
        'wa_link'  => 'https://wa.me/61424475767',
        'instagram'=> 'https://www.instagram.com/jsd.construction/',
    ],
];
