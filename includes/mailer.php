<?php
declare(strict_types=1);

// Подключаем файлы PHPMailer (установлен вручную в vendor/phpmailer)
require_once dirname(__DIR__) . '/vendor/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Отправляет клиенту красивый email-чек с деталями заказа
 *
 * @param array $order Данные заказа (из БД)
 * @throws Exception
 */
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
        
        // Включаем шифрование если порт 465 (SMTPS) или если есть авторизация
        if ($mail->Port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($mail->SMTPAuth) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        
        $fromEmail = env_get('SMTP_FROM') ?? 'info@boyforge.ru';
        $mail->setFrom($fromEmail, 'BOYFORGE');
        $mail->addAddress($order['email'], $order['fio'] ?? '');

        // Собираем HTML шаблон
        ob_start();
        require __DIR__ . '/email_templates/receipt.php';
        $htmlBody = ob_get_clean();

        $mail->isHTML(true);
        $mail->Subject = 'Ваш заказ #' . ($order['order_id'] ?? 'N/A') . ' в BOYFORGE';
        $mail->Body    = $htmlBody;
        
        // Plain text версия для старых клиентов
        $mail->AltBody = "Ваш заказ #{$order['order_id']} успешно оплачен.\nТовар: {$order['product_name']}\nСумма: {$order['price']} руб.\n\nСпасибо за покупку в BOYFORGE!";

        $mail->send();
    } catch (Exception $e) {
        throw new Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}
