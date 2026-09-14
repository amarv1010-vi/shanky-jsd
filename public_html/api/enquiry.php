<?php
/**
 * POST /api/enquiry.php
 * Receives the enquiry form, stores it in `enquiries`, notifies the JSD inbox,
 * and sends the customer an automated email (+ WhatsApp when configured).
 * Body (JSON): name, mobile, email, purpose, area, contact_number?, message
 */

require_once __DIR__ . '/bootstrap.php';

$in = read_input();

// honeypot: bots fill every field; humans never see "website"
if (!empty($in['website'])) {
    json_response(['ok' => true, 'message' => 'Thank you.']);
}

require_fields($in, ['name', 'mobile', 'email', 'purpose', 'area', 'message']);

if (!valid_email($in['email'])) {
    json_response(['ok' => false, 'error' => 'Invalid email address'], 422);
}
if (!valid_phone($in['mobile'])) {
    json_response(['ok' => false, 'error' => 'Invalid mobile number'], 422);
}

throttle('enquiries');

$stmt = db()->prepare(
    'INSERT INTO enquiries (name, mobile, email, purpose, area, contact_number, message, ip_address)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    mb_substr($in['name'], 0, 120),
    mb_substr($in['mobile'], 0, 30),
    mb_substr($in['email'], 0, 190),
    mb_substr($in['purpose'], 0, 120),
    mb_substr($in['area'], 0, 190),
    mb_substr($in['contact_number'] ?? '', 0, 30),
    mb_substr($in['message'], 0, 5000),
    client_ip(),
]);
$id = (int) db()->lastInsertId();

$name = htmlspecialchars($in['name'], ENT_QUOTES, 'UTF-8');

// 1) Automated thank-you email to the customer
$customerHtml = Mailer::template(
    "Thank you for contacting JSD Construction, {$name}",
    "<p>We've received your enquiry (reference <b>#JSD-" . str_pad((string) $id, 5, '0', STR_PAD_LEFT) . "</b>)
     regarding <b>" . htmlspecialchars($in['purpose'], ENT_QUOTES, 'UTF-8') . "</b> in
     <b>" . htmlspecialchars($in['area'], ENT_QUOTES, 'UTF-8') . "</b>.</p>
     <p>Our founder-led team reviews every enquiry personally and will be in touch
     within <b>one business day</b>. If your matter is urgent, call or WhatsApp us
     on <b>+61 424 475 767</b>.</p>
     <p>— The JSD Construction Team</p>"
);
$emailSent = Mailer::send($in['email'], 'Thank you for contacting JSD Construction Pty Ltd', $customerHtml);

// 2) Automated WhatsApp confirmation (active once Cloud API is configured)
$waSent = WhatsApp::sendTemplate($in['mobile'], [$in['name']]);

// 3) Internal notification to the JSD inbox
global $CONFIG;
$adminHtml = Mailer::template(
    'New website enquiry #JSD-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
    '<table style="width:100%;font-size:14px;color:#A89C8A" cellpadding="6">'
    . '<tr><td style="color:#CFA168">Name</td><td>' . $name . '</td></tr>'
    . '<tr><td style="color:#CFA168">Mobile</td><td>' . htmlspecialchars($in['mobile'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="color:#CFA168">Email</td><td>' . htmlspecialchars($in['email'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="color:#CFA168">Purpose</td><td>' . htmlspecialchars($in['purpose'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="color:#CFA168">Area</td><td>' . htmlspecialchars($in['area'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="color:#CFA168">Alt. contact</td><td>' . htmlspecialchars($in['contact_number'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><td style="color:#CFA168">Message</td><td>' . nl2br(htmlspecialchars($in['message'], ENT_QUOTES, 'UTF-8')) . '</td></tr>'
    . '</table><p>Manage all enquiries in the <a href="' . $CONFIG['site']['url'] . '/admin/" style="color:#CFA168">admin panel</a>.</p>'
);
Mailer::send($CONFIG['notify']['enquiry'], "New enquiry from {$in['name']} — {$in['purpose']}", $adminHtml, $in['email']);

json_response([
    'ok'       => true,
    'id'       => $id,
    'email'    => $emailSent,
    'whatsapp' => $waSent,
    'message'  => 'Thank you — your enquiry has been received. A confirmation has been sent to your email' . ($waSent ? ' and WhatsApp' : '') . '. We\'ll be in touch within one business day.',
]);
