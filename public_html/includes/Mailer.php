<?php

declare(strict_types=1);

final class Mailer
{
    private static $lastError = null;

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    public static function send(array $config, string $to, string $subject, string $body): bool
    {
        self::$lastError = null;
        $mail = $config['mail'];
        $ports = [(int) ($mail['smtp_port'] ?? 587), 465, 587];
        $ports = array_values(array_unique(array_filter($ports)));

        foreach ($ports as $port) {
            if (self::sendSmtp($mail, $port, $to, $subject, $body)) {
                return true;
            }
        }

        if (self::$lastError === null) {
            self::$lastError = 'All SMTP connection attempts failed.';
        }

        return false;
    }

    private static function sendSmtp(array $mail, int $port, string $to, string $subject, string $body): bool
    {
        $host = $mail['smtp_host'] ?? 'smtp.gmail.com';
        $user = $mail['smtp_user'] ?? '';
        $pass = str_replace(' ', '', (string) ($mail['smtp_pass'] ?? ''));
        $from = $mail['from_email'] ?? $user;
        $fromName = $mail['from_name'] ?? 'Nexus API Store';

        if ($user === '' || $pass === '' || str_contains($pass, 'your-gmail')) {
            self::$lastError = 'Gmail SMTP password is not configured in config.php.';
            return false;
        }

        $socket = null;

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);

            $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
            $socket = @stream_socket_client(
                $remote,
                $errno,
                $errstr,
                20,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                self::$lastError = "Port {$port}: connection failed ({$errno}) {$errstr}";
                return false;
            }

            stream_set_timeout($socket, 20);

            if (!self::expect($socket, [220], 'greeting')) {
                self::$lastError = "Port {$port}: invalid greeting - " . self::$lastError;
                return false;
            }

            if (!self::command($socket, 'EHLO nexusapistore.com', [250], 'EHLO')) {
                self::$lastError = "Port {$port}: EHLO failed - " . self::$lastError;
                return false;
            }

            if ($port === 587) {
                if (!self::command($socket, 'STARTTLS', [220], 'STARTTLS')) {
                    self::$lastError = "Port {$port}: STARTTLS failed - " . self::$lastError;
                    return false;
                }

                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }

                if (!stream_socket_enable_crypto($socket, true, $crypto)) {
                    self::$lastError = "Port {$port}: TLS negotiation failed.";
                    return false;
                }

                if (!self::command($socket, 'EHLO nexusapistore.com', [250], 'EHLO after STARTTLS')) {
                    self::$lastError = "Port {$port}: EHLO after TLS failed - " . self::$lastError;
                    return false;
                }
            }

            if (!self::command($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN')) {
                self::$lastError = "Port {$port}: AUTH LOGIN failed - " . self::$lastError;
                return false;
            }
            if (!self::command($socket, base64_encode($user), [334], 'SMTP username')) {
                self::$lastError = "Port {$port}: SMTP username rejected - " . self::$lastError;
                return false;
            }
            if (!self::command($socket, base64_encode($pass), [235], 'SMTP password')) {
                self::$lastError = "Port {$port}: SMTP authentication failed. Check Gmail app password.";
                return false;
            }

            if (!self::command($socket, 'MAIL FROM:<' . $from . '>', [250], 'MAIL FROM')) {
                self::$lastError = "Port {$port}: MAIL FROM failed - " . self::$lastError;
                return false;
            }
            if (!self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO')) {
                self::$lastError = "Port {$port}: RCPT TO failed - " . self::$lastError;
                return false;
            }
            if (!self::command($socket, 'DATA', [354], 'DATA')) {
                self::$lastError = "Port {$port}: DATA command failed - " . self::$lastError;
                return false;
            }

            $message = self::buildMessage($fromName, $from, $to, $subject, $body);
            if (!self::command($socket, $message, [250], 'message body')) {
                self::$lastError = "Port {$port}: message rejected - " . self::$lastError;
                return false;
            }

            self::command($socket, 'QUIT', [221, 250], 'QUIT');
            fclose($socket);
            self::$lastError = null;
            return true;
        } catch (Throwable $e) {
            self::$lastError = "Port {$port}: " . $e->getMessage();
            if (is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }
    }

    private static function buildMessage(string $fromName, string $from, string $to, string $subject, string $body): string
    {
        $safeBody = preg_replace('/^\./m', '..', $body) ?? $body;

        $headers = [
            'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . uuid_v4() . '@nexusapistore.com>',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $safeBody . "\r\n.";
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }

    private static function command($socket, string $data, array $expectedCodes, string $step): bool
    {
        self::write($socket, $data);
        return self::expect($socket, $expectedCodes, $step);
    }

    private static function expect($socket, array $expectedCodes, string $step): bool
    {
        $response = self::read($socket);
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            self::$lastError = trim($step . ': ' . $response);
            return false;
        }

        return true;
    }

    private static function write($socket, string $data): void
    {
        fwrite($socket, $data . "\r\n");
    }

    private static function read($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }
}
