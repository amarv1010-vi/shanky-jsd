<?php
/**
 * POST /api/contact.php
 * Contact-page form: stores the message and forwards it to the chosen
 * JSD mailbox (contact@ by default; topic maps to quotes@/projects@/support@).
 * Body (JSON): name, email, mobile, topic?, message
 */

require_once __DIR__ . '/bootstrap.php';

$in = read_input();

if (!empty($in['website'])) { // honeypot
    json_response(['ok' => true, 'message' => 'Thank you.']);
}

require_fields($in, ['name', 'email', 'mobile', 'message']);

if (!valid_email($in['email'])) {
    json_response(['ok' => false, 'error' => 'Invalid email address'], 422);
}
if (!valid_phone($in['mobile'])) {
    json_response(['ok' => false, 'error' => 'Invalid phone number'], 422);
}

throttle('contact_messages');

global $CONFIG;
$topic = $in['topic'] ?? 'general';
$routing = [
    'general'  => $CONFIG['notify']['contact'],
    'quotes'   => $CONFIG['notify']['quotes'],
    'projects' => $CONFIG['notify']['projects'],
    'support'  => $CONFIG['notify']['support'],
];
$recipient = $routing[$topic] ?? $CONFIG['notify']['contact'];

$stmt = db()->prepare(
    'INSERT INTO contact_messages (name, email, mobile, topic, message, routed_to, ip_address)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    mb_substr($in['name'], 0, 120),
    mb_substr($in['email'], 0, 190),
    mb_substr($in['mobile'], 0, 30),
    mb_substr($topic, 0, 40),
    mb_substr($in['message'], 0, 5000),
    $recipient,
    client_ip(),
]);
$id = (int) db()->lastInsertId();

$name = htmlspecialchars($in['name'], ENT_QUOTES, 'UTF-8');

// forward to JSD mailbox with Reply-To set to the customer
$adminHtml = Mailer::template(
    'New contact message #' . $id,
    '<p><b style="color:#CFA168">From:</b> ' . $name . ' &lt;' . htmlspecialchars($in['email'], ENT_QUOTES, 'UTF-8') . '&gt;<br>'
    . '<b style="color:#CFA168">Phone:</b> ' . htmlspecialchars($in['mobile'], ENT_QUOTES, 'UTF-8') . '<br>'
    . '<b style="color:#CFA168">Topic:</b> ' . htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p>' . nl2br(htmlspecialchars($in['message'], ENT_QUOTES, 'UTF-8')) . '</p>'
);
Mailer::send($recipient, "Website contact ({$topic}) from {$in['name']}", $adminHtml, $in['email']);

// automated acknowledgement to the customer
$ackHtml = Mailer::template(
    "Thanks for reaching out, {$name}",
    '<p>Your message has been delivered to <b>' . htmlspecialchars($recipient, ENT_QUOTES, 'UTF-8') . '</b>.
     We aim to respond within one business day.</p>
     <p>— The JSD Construction Team</p>'
);
Mailer::send($in['email'], 'We received your message — JSD Construction', $ackHtml);

json_response([
    'ok'      => true,
    'id'      => $id,
    'message' => 'Message sent to ' . $recipient . '. We\'ll reply within one business day.',
]);
