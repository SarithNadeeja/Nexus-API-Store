<?php

declare(strict_types=1);

/**
 * One-time SMTP test. Visit /test-mail.php?to=you@example.com then DELETE this file.
 */

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    exit('config.php missing');
}

$config = require $configPath;
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/Mailer.php';

$to = trim((string) ($_GET['to'] ?? $config['mail']['smtp_user']));
$subject = 'Nexus API Store SMTP Test';
$body = "This is a test email from Nexus API Store.\nSent at: " . date('c');

$sent = Mailer::send($config, $to, $subject, $body);
$error = Mailer::getLastError();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SMTP Test</title>
  <style>
    body { font-family: Arial, sans-serif; background:#0b1020; color:#e5e7eb; padding:2rem; }
    .ok { color:#34d399; }
    .bad { color:#f87171; }
    code { background:#111827; padding:.2rem .4rem; border-radius:.35rem; }
  </style>
</head>
<body>
  <h1>SMTP Test</h1>
  <p>To: <code><?= htmlspecialchars($to) ?></code></p>
  <p>Result:
    <strong class="<?= $sent ? 'ok' : 'bad' ?>"><?= $sent ? 'SENT' : 'FAILED' ?></strong>
  </p>
  <?php if ($error): ?>
    <p class="bad"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>
  <?php if ($sent): ?>
    <p class="ok">Check inbox and spam folder. Delete <code>test-mail.php</code> after testing.</p>
  <?php else: ?>
    <p>Common fixes:</p>
    <ul>
      <li>Confirm Gmail app password in <code>config.php</code></li>
      <li>Ensure server allows outbound ports 587 and 465</li>
      <li>Enable 2-Step Verification and App Passwords on the Gmail account</li>
    </ul>
  <?php endif; ?>
</body>
</html>
