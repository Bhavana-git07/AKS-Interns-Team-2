<?php
// config/mailer.php
// Pure-PHP SMTP Mailer client that sends verification emails to real addresses.

class SimpleMailer {
    public static function send($to, $subject, $otpCode) {
        $smtp_host = getenv('SMTP_HOST') ?: '';
        $smtp_port = intval(getenv('SMTP_PORT') ?: 587);
        $smtp_user = getenv('SMTP_USER') ?: '';
        $smtp_pass = getenv('SMTP_PASS') ?: '';
        $smtp_from = getenv('SMTP_FROM') ?: 'no-reply@complianceaudit.com';
        $smtp_from_name = getenv('SMTP_FROM_NAME') ?: 'Compliance Audit Platform';

        // Check for local .env file in the backend directory
        $env_path = __DIR__ . '/../.env';
        if (file_exists($env_path)) {
            $env_lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($env_lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, "\"' \t");
                    if ($key === 'SMTP_HOST') $smtp_host = $value;
                    elseif ($key === 'SMTP_PORT') $smtp_port = intval($value);
                    elseif ($key === 'SMTP_USER') $smtp_user = $value;
                    elseif ($key === 'SMTP_PASS') $smtp_pass = $value;
                    elseif ($key === 'SMTP_FROM') $smtp_from = $value;
                    elseif ($key === 'SMTP_FROM_NAME') $smtp_from_name = $value;
                }
            }
        }

        // Fallback output logging for dev convenience
        $log_content = "To: $to\nSubject: $subject\nOTP Code: $otpCode\nSent at: " . date('Y-m-d H:i:s') . "\n-------------------------\n";
        file_put_contents(__DIR__ . "/../otp_output.txt", $log_content, FILE_APPEND);

        // Build email headers
        $headers = "From: =?UTF-8?B?" . base64_encode($smtp_from_name) . "?= <$smtp_from>\r\n";
        $headers .= "Reply-To: $smtp_from\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Beautiful HTML email body matching the dark design system
        $html_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #0d1117; color: #ffffff; padding: 20px; }
                .card { background-color: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 24px; max-width: 480px; margin: 0 auto; }
                h2 { color: #3dd6ac; margin-top: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                .code { font-size: 26px; font-weight: bold; letter-spacing: 5px; color: #3dd6ac; background: #0d1117; padding: 14px; border-radius: 6px; text-align: center; margin: 20px 0; border: 1px dashed #30363d; }
                p { line-height: 1.5; color: #c9d1d9; }
                .footer { font-size: 11px; color: #8b949e; margin-top: 24px; border-top: 1px solid #30363d; padding-top: 12px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h2>ComplianceAudit Verification</h2>
                <p>Hello,</p>
                <p>Use the following 6-digit One-Time Password (OTP) to log in to your account. This code is valid for 5 minutes.</p>
                <div class='code'>$otpCode</div>
                <p>If you did not request this verification code, please ignore this email or secure your account settings.</p>
                <div class='footer'>
                    This is an automated system security message. Please do not reply.
                </div>
            </div>
        </body>
        </html>";

        // Try sending via SMTP socket if host is configured
        if (!empty($smtp_host)) {
            try {
                $sent = self::sendSmtpSocket($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_from, $to, $subject, $html_message, $headers);
                if ($sent) return true;
            } catch (Exception $e) {
                error_log("SimpleMailer SMTP error: " . $e->getMessage());
            }
        }

        // Fallback to PHP native mailer
        return mail($to, $subject, $html_message, $headers);
    }

    private static function sendSmtpSocket($host, $port, $user, $pass, $from, $to, $subject, $body, $headers) {
        $socket = fsockopen($host, $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        $read = function($socket) {
            $res = "";
            while ($line = fgets($socket, 515)) {
                $res .= $line;
                if (substr($line, 3, 1) === " ") break;
            }
            return $res;
        };

        $send = function($socket, $cmd) use ($read) {
            fputs($socket, $cmd . "\r\n");
            return $read($socket);
        };

        $read($socket); // read initial server connection header
        $send($socket, "EHLO " . $_SERVER['SERVER_NAME']);

        if ($port === 587) {
            $send($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Encryption failed");
            }
            $send($socket, "EHLO " . $_SERVER['SERVER_NAME']); // re-handshake after encryption
        }

        if (!empty($user) && !empty($pass)) {
            $send($socket, "AUTH LOGIN");
            $send($socket, base64_encode($user));
            $send($socket, base64_encode($pass));
        }

        $send($socket, "MAIL FROM: <$from>");
        $send($socket, "RCPT TO: <$to>");
        $send($socket, "DATA");

        // Send full headers & body
        $data = "To: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" . $headers . "\r\n\r\n" . $body . "\r\n.";
        $send($socket, $data);

        $send($socket, "QUIT");
        fclose($socket);
        return true;
    }
}
?>
