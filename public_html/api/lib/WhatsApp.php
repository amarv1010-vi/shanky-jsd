<?php
/**
 * WhatsApp automated replies via the Meta WhatsApp Business Cloud API.
 *
 * Requires a WhatsApp Business account with +61 424 475 767 registered and an
 * approved message template (see api/config.example.php for setup steps).
 * When disabled/unconfigured, sends nothing and returns false — the customer
 * still receives the email auto-reply, which contains a wa.me chat link.
 */

class WhatsApp
{
    /**
     * Send the approved "enquiry received" template to a customer.
     * $params fills the template's {{1}}, {{2}}… placeholders (e.g. [name]).
     */
    public static function sendTemplate(string $toPhone, array $params = []): bool
    {
        global $CONFIG;
        $wa = $CONFIG['whatsapp'];
        if (empty($wa['enabled']) || empty($wa['access_token']) || empty($wa['phone_number_id'])) {
            return false;
        }

        $to = self::normalisePhone($toPhone);
        if ($to === null) {
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $wa['template_name'],
                'language' => ['code' => $wa['template_lang']],
            ],
        ];
        if ($params) {
            $payload['template']['components'] = [[
                'type'       => 'body',
                'parameters' => array_map(
                    fn($p) => ['type' => 'text', 'text' => (string) $p],
                    $params
                ),
            ]];
        }

        $ch = curl_init("https://graph.facebook.com/v21.0/{$wa['phone_number_id']}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $wa['access_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            error_log("WhatsApp API error ({$code}): " . substr((string) $res, 0, 400));
            return false;
        }
        return true;
    }

    /** Convert AU numbers to E.164 without '+' (Cloud API format), e.g. 0424… -> 61424… */
    private static function normalisePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') return null;
        if (str_starts_with($digits, '61'))  return $digits;
        if (str_starts_with($digits, '0'))   return '61' . substr($digits, 1);
        if (strlen($digits) >= 8)            return '61' . $digits; // assume AU local
        return null;
    }
}
