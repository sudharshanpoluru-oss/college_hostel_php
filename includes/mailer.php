<?php

function sendMail($to, $subject, $htmlBody): bool
{
    if (!defined('SMTP_USER') || SMTP_USER === 'yourgmail@gmail.com' || SMTP_PASS === 'your_app_password') {
        return false;
    }

    $smtp = @fsockopen('ssl://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, 15);
    if (!$smtp) return false;

    $read = function () use ($smtp) {
        $data = '';
        while (($line = fgets($smtp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $data;
    };

    $cmd = function ($command, $expect) use ($smtp, $read) {
        fwrite($smtp, $command . "\r\n");
        return strpos($read(), (string)$expect) === 0;
    };

    $ok = true;
    try {
        if (strpos($read(), '220') !== 0) throw new Exception();
        if (!$cmd('EHLO localhost', 250)) throw new Exception();
        if (!$cmd('AUTH LOGIN', 334)) throw new Exception();
        if (!$cmd(base64_encode(SMTP_USER), 334)) throw new Exception();
        if (!$cmd(base64_encode(SMTP_PASS), 235)) throw new Exception();

        $from = SMTP_USER;
        $headers = "From: " . SITE_NAME . " <{$from}>\r\n"
                 . "To: <{$to}>\r\n"
                 . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n";

        if (!$cmd("MAIL FROM:<{$from}>", 250)) throw new Exception();
        if (!$cmd("RCPT TO:<{$to}>", 250)) throw new Exception();
        if (!$cmd('DATA', 354)) throw new Exception();

        fwrite($smtp, $headers . "\r\n" . $htmlBody . "\r\n.\r\n");
        if (strpos($read(), '250') !== 0) throw new Exception();
        $cmd('QUIT', 221);
    } catch (Exception $e) {
        $ok = false;
    }

    fclose($smtp);
    return $ok;
}
