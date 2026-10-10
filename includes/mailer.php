<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOrderReceipt(array $order): void {
    if (empty($order['email'])) {
        return;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = env_get('SMTP_HOST') ?? 'localhost';
        $mail->SMTPAuth   = !empty(env_get('SMTP_USER'));
        $mail->Username   = env_get('SMTP_USER') ?? '';
        $mail->Password   = env_get('SMTP_PASS') ?? '';
        
        $smtpPort = env_get('SMTP_PORT');
        $mail->Port = $smtpPort ? (int)$smtpPort : 587;
        
        if ($mail->Port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($mail->SMTPAuth) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        
        $fromEmail = env_get('SMTP_FROM') ?? 'info@boyforge.ru';
        $mail->setFrom($fromEmail, 'BOYFORGE');
        $mail->addAddress($order['email'], $order['fio'] ?? '');

        ob_start();
        require __DIR__ . '/email_templates/receipt.php';
        $htmlBody = ob_get_clean();

        $mail->isHTML(true);
        $mail->Subject = 'Ваш заказ #' . ($order['order_id'] ?? 'N/A') . ' в BOYFORGE';
        $mail->Body    = $htmlBody;
        
        $mail->AltBody = "Спасибо за покупку!\nВаш заказ #{$order['order_id']} успешно оплачен и передан в обработку.\nТовар: {$order['product_name']}\nСумма: {$order['price']} руб.\n\nВ ближайшее время (3-4 дня) мы передадим заказ в службу доставки 5POST.\nОтслеживать заказ можете на сайте: https://fivepost.ru";

        $mail->send();
    } catch (Exception $e) {
        throw new Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}
