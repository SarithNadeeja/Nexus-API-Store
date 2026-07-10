<?php

declare(strict_types=1);

final class Mailer
{
    public static function send(array $config, string $to, string $subject, string $body): bool
    {
        $mail = $config['mail'];
        $ports = [587, 465];

        foreach ($ports as $port) {
            if (self::sendSmtp($mail, $port, $to, $subject, $body)) {
                return true;
            }
        }

        return false;
    }

    private static function sendSmtp(array $mail, int $port, string $to, string $subject, string $body): bool
    {
        $host = $mail['smtp_host'];
        $user = $mail['smtp_user'];
        $pass = str_replace(' ', '', $mail['smtp_pass']);
        $from = $mail['from_email'];
        $fromName = $mail['from_name'] ?? 'Nexus API Store';

        try {
            $remote = ($port === 465 ? 'ssl://' : '') . $host;
            $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 15);
            if (!$socket) {
                return false;
            }

            self::read($socket);
            self::write($socket, 'EHLO nexusapistore.com');
            self::read($socket);

            if ($port === 587) {
                self::write($socket, 'STARTTLS');
                self::read($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                self::write($socket, 'EHLO nexusapistore.com');
                self::read($socket);
            }

            self::write($socket, 'AUTH LOGIN');
            self::read($socket);
            self::write($socket, base64_encode($user));
            self::read($socket);
            self::write($socket, base64_encode($pass));
            self::read($socket);
            self::write($socket, 'MAIL FROM:<' . $from . '>');
            self::read($socket);
            self::write($socket, 'RCPT TO:<' . $to . '>');
            self::read($socket);
            self::write($socket, 'DATA');
            self::read($socket);

            $headers = [
                'From: ' . $fromName . ' <' . $from . '>',
                'Reply-To: ' . $from,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'To: <' . $to . '>',
                'Subject: ' . $subject,
            ];

            self::write($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
            self::read($socket);
            self::write($socket, 'QUIT');
            fclose($socket);
            return true;
        } catch (Throwable) {
            return false;
        }
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
