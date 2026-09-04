<?php
// Wrapper pengiriman email.
// - Jika mail_config.php berisi SMTP, gunakan PHPMailer via SMTP.
// - Jika tidak, coba fungsi mail() bawaan PHP (sering diblokir di free hosting).

function sendMail($to, $subject, $body) {
    $config = [];
    $configPath = __DIR__ . '/mail_config.php';
    if (file_exists($configPath)) {
        $config = include $configPath;
        if (!is_array($config)) $config = [];
    }

    if (!empty($config['smtp_host']) && !empty($config['smtp_username']) && !empty($config['smtp_password'])) {
        if (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
            require_once __DIR__ . '/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/PHPMailer/SMTP.php';
            require_once __DIR__ . '/PHPMailer/Exception.php';
        }
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $msg = 'PHPMailer tidak ditemukan.';
            error_log($msg);
            return ['success' => false, 'error' => $msg];
        }
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_username'];
            $mail->Password = $config['smtp_password'];
            $secure = strtolower($config['smtp_secure'] ?? 'tls');
            $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)($config['smtp_port'] ?? 587);
            $mail->setFrom($config['from'] ?? 'no-reply@gamekartuduasatu.freepage.cc', $config['from_name'] ?? '21: Ruang Lilin');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return ['success' => true, 'error' => ''];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            error_log('SMTP mail failed: ' . $msg);
            return ['success' => false, 'error' => $msg];
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            error_log('SMTP mail error: ' . $msg);
            return ['success' => false, 'error' => $msg];
        }
    }

    $from = $config['from'] ?? 'no-reply@gamekartuduasatu.freepage.cc';
    $headers = "From: $from\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    $sent = mail($to, $subject, $body, $headers);
    if ($sent) {
        return ['success' => true, 'error' => ''];
    }
    $msg = 'mail() gagal atau diblokir hosting.';
    error_log($msg);
    return ['success' => false, 'error' => $msg];
}
