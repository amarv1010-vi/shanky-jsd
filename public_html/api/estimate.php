<?php
/**
 * POST /api/estimate.php
 * Cost-estimator lead capture: stores the lead + the indicative range shown,
 * emails the customer their estimate and alerts quotes@jsdconstruction.com.au.
 * Body (JSON): name, email, mobile, build_type, spec_level, floor_area, slope, estimate_range
 */

require_once __DIR__ . '/bootstrap.php';

$in = read_input();

if (!empty($in['website'])) { // honeypot
    json_response(['ok' => true, 'message' => 'Thank you.']);
}

require_fields($in, ['name', 'email', 'mobile', 'build_type', 'spec_level', 'floor_area']);

if (!valid_email($in['email'])) {
    json_response(['ok' => false, 'error' => 'Invalid email address'], 422);
}
if (!valid_phone($in['mobile'])) {
    json_response(['ok' => false, 'error' => 'Invalid phone number'], 422);
}

throttle('estimates');

$stmt = db()->prepare(
    'INSERT INTO estimates (name, email, mobile, build_type, spec_level, floor_area, slope, estimate_range, ip_address)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    mb_substr($in['name'], 0, 120),
    mb_substr($in['email'], 0, 190),
    mb_substr($in['mobile'], 0, 30),
    mb_substr($in['build_type'], 0, 40),
    mb_substr($in['spec_level'], 0, 40),
    (float) $in['floor_area'],
    mb_substr($in['slope'] ?? 'flat', 0, 20),
    mb_substr($in['estimate_range'] ?? '', 0, 80),
    client_ip(),
]);
$id = (int) db()->lastInsertId();

global $CONFIG;
$name  = htmlspecialchars($in['name'], ENT_QUOTES, 'UTF-8');
$range = htmlspecialchars($in['estimate_range'] ?: 'to be confirmed', ENT_QUOTES, 'UTF-8');
$type  = htmlspecialchars($in['build_type'], ENT_QUOTES, 'UTF-8');
$area  = htmlspecialchars((string) $in['floor_area'], ENT_QUOTES, 'UTF-8');

$customerHtml = Mailer::template(
    "Your indicative build estimate, {$name}",
    "<p>Based on the details you provided (<b>{$type}</b>, {$area} m²,
     " . htmlspecialchars($in['spec_level'], ENT_QUOTES, 'UTF-8') . " specification), your indicative
     build range is:</p>
     <p style='font-size:22px;color:#E0B171;font-family:Georgia,serif'>{$range}</p>
     <p>This is a guide only. Our estimating team will now prepare a detailed,
     site-specific quote and contact you within <b>2 business days</b>.</p>
     <p>— The JSD Construction Team</p>"
);
Mailer::send($in['email'], 'Your JSD Construction build estimate', $customerHtml);

$adminHtml = Mailer::template(
    'New estimator lead #' . $id,
    "<p><b style='color:#CFA168'>{$name}</b> — " . htmlspecialchars($in['email'], ENT_QUOTES, 'UTF-8')
    . ' / ' . htmlspecialchars($in['mobile'], ENT_QUOTES, 'UTF-8') . "</p>
     <p>{$type} · {$area} m² · " . htmlspecialchars($in['spec_level'], ENT_QUOTES, 'UTF-8')
    . ' · slope: ' . htmlspecialchars($in['slope'] ?? 'flat', ENT_QUOTES, 'UTF-8') . "</p>
     <p>Shown range: <b>{$range}</b></p>"
);
Mailer::send($CONFIG['notify']['quotes'], "Estimator lead: {$in['name']} ({$type}, {$area} m²)", $adminHtml, $in['email']);

json_response([
    'ok'      => true,
    'id'      => $id,
    'message' => 'Estimate sent to your email. Our team will follow up with a detailed quote within 2 business days.',
]);
