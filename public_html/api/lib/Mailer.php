<?php
/**
 * Minimal dependency-free SMTP mailer for Titan Email (works on GoDaddy cPanel).
 * Speaks SMTP over SSL (port 465) or STARTTLS (port 587) with AUTH LOGIN.
 * Falls back to PHP mail() when SMTP is disabled in config.
 *
 * Usage:
 *   Mailer::send('to@x.com', 'Subject', '<p>HTML body</p>');
 */

class Mailer
{
    /** @return bool true when the message was accepted for delivery */
    public static function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
    {
        global $CONFIG;
        $smtp = $CONFIG['smtp'];

        $fromEmail = $smtp['from'];
        $fromName  = $smtp['from_name'];

        if (empty($smtp['enabled'])) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
            if ($replyTo) $headers .= "Reply-To: {$replyTo}\r\n";
            return @mail($to, $subject, $htmlBody, $headers);
        }

        try {
            return self::smtpSend($smtp, $to, $subject, $htmlBody, $replyTo);
        } catch (Throwable $e) {
            error_log('Mailer SMTP failure: ' . $e->getMessage());
            return false;
        }
    }

    private static function smtpSend(array $smtp, string $to, string $subject, string $htmlBody, ?string $replyTo): bool
    {
        $host   = $smtp['host'];
        $port   = (int) $smtp['port'];
        $secure = $smtp['secure']; // 'ssl' | 'tls'

        $remote = ($secure === 'ssl' ? "ssl://{$host}" : $host) . ":{$port}";
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 15);

        $expect = function (array $codes) use ($fp): string {
            $line = '';
            do {
                $line = fgets($fp, 1024);
                if ($line === false) throw new RuntimeException('SMTP read failed');
            } while (isset($line[3]) && $line[3] === '-'); // skip multiline
            $code = (int) substr($line, 0, 3);
            if (!in_array($code, $codes, true)) {
                throw new RuntimeException("SMTP unexpected reply: {$line}");
            }
            return $line;
        };
        $say = function (string $cmd) use ($fp): void {
            fwrite($fp, $cmd . "\r\n");
        };

        $expect([220]);
        $say('EHLO jsdconstruction.com.au');
        $expect([250]);

        if ($secure === 'tls') {
            $say('STARTTLS');
            $expect([220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            $say('EHLO jsdconstruction.com.au');
            $expect([250]);
        }

        $say('AUTH LOGIN');
        $expect([334]);
        $say(base64_encode($smtp['username']));
        $expect([334]);
        $say(base64_encode($smtp['password']));
        $expect([235]);

        $say('MAIL FROM:<' . $smtp['from'] . '>');
        $expect([250]);
        $say('RCPT TO:<' . $to . '>');
        $expect([250, 251]);
        $say('DATA');
        $expect([354]);

        $fromName = mb_encode_mimeheader($smtp['from_name'], 'UTF-8');
        $headers = [
            'Date: ' . date('r'),
            "From: {$fromName} <{$smtp['from']}>",
            "To: <{$to}>",
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@jsdconstruction.com.au>',
        ];
        if ($replyTo) $headers[] = "Reply-To: <{$replyTo}>";

        $data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody));
        // dot-stuffing per RFC 5321
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($fp, $data . "\r\n.\r\n");
        $expect([250]);

        $say('QUIT');
        fclose($fp);
        return true;
    }

    /** Branded HTML email wrapper used by all automated replies. */
    public static function template(string $heading, string $bodyHtml): string
    {
        global $CONFIG;
        $site = $CONFIG['site'];
        return '
<!DOCTYPE html>
<html><body style="margin:0;padding:0;background:#100E0B;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#100E0B;padding:34px 0">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:94%;background:#1F1B14;border:1px solid #3a2f1f;border-radius:14px;overflow:hidden">
    <tr><td style="background:linear-gradient(135deg,#7B613A,#C1955D,#E0B171,#CFA168,#9F7745);height:5px;font-size:0;line-height:0">&nbsp;</td></tr>
    <tr><td style="padding:38px 40px 10px;text-align:center">
      <div style="font-size:26px;letter-spacing:4px;color:#E0B171;font-family:Georgia,serif">JSD CONSTRUCTION</div>
      <div style="font-size:10px;letter-spacing:5px;color:#9F7745;margin-top:6px">LUXURY BUILT IN AUSTRALIA</div>
    </td></tr>
    <tr><td style="padding:26px 40px 8px">
      <h1 style="color:#EDE6DA;font-size:21px;font-weight:normal;font-family:Georgia,serif;margin:0 0 16px">' . $heading . '</h1>
      <div style="color:#A89C8A;font-size:14px;line-height:1.7">' . $bodyHtml . '</div>
    </td></tr>
    <tr><td style="padding:26px 40px 34px">
      <a href="' . $site['wa_link'] . '" style="display:inline-block;background:#CFA168;color:#17130C;text-decoration:none;font-weight:bold;font-size:13px;padding:12px 26px;border-radius:24px">Chat on WhatsApp</a>
      &nbsp;&nbsp;
      <a href="tel:+61424475767" style="display:inline-block;border:1px solid #9F7745;color:#CFA168;text-decoration:none;font-size:13px;padding:11px 26px;border-radius:24px">Call ' . $site['phone'] . '</a>
    </td></tr>
    <tr><td style="border-top:1px solid #3a2f1f;padding:20px 40px;color:#6f6353;font-size:11px;line-height:1.7">
      JSD Construction Pty Ltd · Brisbane, QLD · QLD Low-Rise Builder Licence<br>
      <a href="' . $site['url'] . '" style="color:#9F7745">' . $site['url'] . '</a> ·
      <a href="' . $site['instagram'] . '" style="color:#9F7745">Instagram</a><br>
      This is an automated message — please do not reply directly to this email.
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>';
    }
}
