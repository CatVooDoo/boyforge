<?php

$orderId = htmlspecialchars((string)($order['order_id'] ?? 'N/A'));
$fio = htmlspecialchars((string)($order['fio'] ?? 'Клиент'));
$productName = htmlspecialchars((string)($order['product_name'] ?? 'Товар'));
$size = htmlspecialchars((string)($order['size'] ?? '-'));
$price = number_format((float)($order['price'] ?? 0), 0, '', ' ') . ' ₽';
$pointAddress = htmlspecialchars((string)($order['fivepost_point_address'] ?? '-'));
$date = date('d.m.Y H:i');

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ваш заказ в BOYFORGE</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f4f4; color: #111111;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f4f4; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 600px; width: 100%;">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color: #111111; padding: 30px 20px;">
                            <img src="https://boyforge.ru/images/logo-white.png" alt="BOYFORGE" width="180" style="display: block; border: 0; max-width: 100%; height: auto;">
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 30px 0; font-size: 16px; line-height: 1.5; color: #555555;">
                                Спасибо за покупку в BOYFORGE. Ваш заказ успешно оплачен и передан в обработку. 
                            </p>

                            <!-- Order Details Box -->
                            <div style="background-color: #f9f9f9; border: 1px solid #eeeeee; border-radius: 6px; padding: 20px; margin-bottom: 30px;">
                                <h3 style="margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; color: #888888; letter-spacing: 1px;">Детали заказа #<?= $orderId ?></h3>
                                
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td style="padding: 8px 0; font-size: 15px; border-bottom: 1px solid #eeeeee; color: #555555;">Товар:</td>
                                        <td align="right" style="padding: 8px 0; font-size: 15px; font-weight: 600; border-bottom: 1px solid #eeeeee;"><?= $productName ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; font-size: 15px; border-bottom: 1px solid #eeeeee; color: #555555;">Размер:</td>
                                        <td align="right" style="padding: 8px 0; font-size: 15px; font-weight: 600; border-bottom: 1px solid #eeeeee;"><?= $size ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; font-size: 15px; border-bottom: 1px solid #eeeeee; color: #555555;">Доставка (5Post):</td>
                                        <td align="right" style="padding: 8px 0; font-size: 15px; font-weight: 600; border-bottom: 1px solid #eeeeee; text-align: right; line-height: 1.4;"><?= $pointAddress ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 15px 0 0 0; font-size: 16px; font-weight: bold; color: #111111;">Итого оплачено:</td>
                                        <td align="right" style="padding: 15px 0 0 0; font-size: 18px; font-weight: bold; color: #111111;"><?= $price ?></td>
                                    </tr>
                                </table>
                            </div>

                            <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.5; color: #555555;">
                                В ближайшее время мы передадим заказ в службу доставки 5Post. Вы получите уведомление с трек-номером на ваш телефон.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #fafafa; border-top: 1px solid #eeeeee; padding: 20px; font-size: 13px; color: #999999;">
                            <p style="margin: 0 0 5px 0;">&copy; <?= date('Y') ?> BOYFORGE. Все права защищены.</p>
                            <p style="margin: 0;">Если у вас есть вопросы, просто ответьте на это письмо.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
