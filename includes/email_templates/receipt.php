<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/order_items.php';

$orderId = htmlspecialchars((string)($order['order_id'] ?? 'N/A'));
$fio = htmlspecialchars((string)($order['fio'] ?? 'Клиент'));
$items = normalizeOrderItems($order);
$isMultiOrder = count($items) > 1 || (int)($items[0]['qty'] ?? 1) > 1;
$goodsSum = orderItemsGoodsTotal($items);
if ($goodsSum <= 0) {
    $goodsSum = (float)($order['price'] ?? 0);
}
$deliveryCostVal = (float)($order['delivery_cost'] ?? 0);
$price = number_format($goodsSum + $deliveryCostVal, 2, '.', ' ') . ' ₽';
$carrier = ($order['delivery_provider'] ?? 'fivepost') === 'cdek' ? 'СДЭК' : '5Post';
$trackingUrl = $carrier === 'СДЭК' ? 'https://www.cdek.ru/ru/tracking/' : 'https://fivepost.ru';
$testDelivery = $carrier === 'СДЭК' && ($order['delivery_mode'] ?? '') === 'sandbox';
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
                                Спасибо за покупку, <?= $fio ?>!<br>
                                Ваш заказ успешно оплачен и передан в обработку.
                            </p>

                            <!-- Order Details Box -->
                            <div style="background-color: #f9f9f9; border: 1px solid #eeeeee; border-radius: 6px; padding: 20px; margin-bottom: 30px;">
                                <h3 style="margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; color: #888888; letter-spacing: 1px;">Детали заказа #<?= $orderId ?></h3>
                                
                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <?php if (!$isMultiOrder): ?>
                                        <?php
                                            $single = $items[0];
                                            $singleName = htmlspecialchars((string)$single['name']);
                                            $singleVariant = implode(', ', array_filter([(string)$single['gender'], (string)$single['size']], static fn($v) => $v !== ''));
                                            if ($singleVariant === '') {
                                                $singleVariant = (string)($order['size'] ?? '-');
                                            }
                                        ?>
                                        <tr>
                                            <td style="padding: 8px 0; font-size: 15px; border-bottom: 1px solid #eeeeee; color: #555555;">Товар:</td>
                                            <td align="right" style="padding: 8px 0; font-size: 15px; font-weight: 600; border-bottom: 1px solid #eeeeee;"><?= $singleName ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 0; font-size: 15px; border-bottom: 1px solid #eeeeee; color: #555555;">Параметры:</td>
                                            <td align="right" style="padding: 8px 0; font-size: 15px; font-weight: 600; border-bottom: 1px solid #eeeeee;"><?= htmlspecialchars($singleVariant) ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="2" style="padding: 4px 0 10px 0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #888888; border-bottom: 1px solid #e5e5e5;">
                                                Состав заказа (<?= orderTotalUnits($order) ?> шт.)
                                            </td>
                                        </tr>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                                $iName = htmlspecialchars((string)$item['name']);
                                                $iVariant = implode(', ', array_filter([(string)$item['gender'], (string)$item['size']], static fn($v) => $v !== ''));
                                                $iQty = max(1, (int)$item['qty']);
                                                $iUnitPrice = (float)$item['price'];
                                                $iLineSum = number_format($iUnitPrice * $iQty, 2, '.', ' ') . ' ₽';
                                            ?>
                                            <tr>
                                                <td style="padding: 10px 8px 10px 0; font-size: 14px; border-bottom: 1px solid #eeeeee; color: #111111; line-height: 1.4;">
                                                    <div style="font-weight: 600;"><?= $iName ?></div>
                                                    <?php if ($iVariant !== ''): ?>
                                                        <div style="font-size: 12px; color: #666666; margin-top: 2px;"><?= htmlspecialchars($iVariant) ?> · <?= $iQty ?> шт. × <?= number_format($iUnitPrice, 2, '.', ' ') ?> ₽</div>
                                                    <?php else: ?>
                                                        <div style="font-size: 12px; color: #666666; margin-top: 2px;"><?= $iQty ?> шт. × <?= number_format($iUnitPrice, 2, '.', ' ') ?> ₽</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td align="right" valign="top" style="padding: 10px 0; font-size: 14px; font-weight: 600; border-bottom: 1px solid #eeeeee; white-space: nowrap;"><?= $iLineSum ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($order['promo_code'])): ?>
                                    <tr><td colspan="2" style="padding:10px 0; font-size:12px; color:#2e7d32;">Промокод <?= htmlspecialchars((string)$order['promo_code']) ?>: скидка <?= number_format((float)($order['discount_amount'] ?? 0), 2, '.', ' ') ?> ₽ уже учтена в ценах товаров.</td></tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td style="padding: 8px 0; font-size: 15px; border-bottom: 1px solid #eeeeee; color: #555555;">Доставка (<?= $carrier ?>):</td>
                                        <td align="right" style="padding: 8px 0; font-size: 15px; font-weight: 600; border-bottom: 1px solid #eeeeee; text-align: right; line-height: 1.4;"><?= $pointAddress ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 15px 0 0 0; font-size: 16px; font-weight: bold; color: #111111;">Итого оплачено:</td>
                                        <td align="right" style="padding: 15px 0 0 0; font-size: 18px; font-weight: bold; color: #111111;"><?= $price ?></td>
                                    </tr>
                                    <?php if ($deliveryCostVal > 0): ?>
                                    <tr>
                                        <td style="padding-top: 8px; font-size: 13px; color: #555555;">В том числе доставка:</td>
                                        <td align="right" style="padding-top: 8px; font-size: 13px;"><?= number_format($deliveryCostVal, 2, '.', ' ') ?> ₽</td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>

                            <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.5; color: #555555;">
                                <?= $testDelivery ? 'Это тестовый заказ.' : 'В ближайшее время передадим заказ в службу доставки.' ?><br>
                                Служба доставки: <?= $carrier ?><?= $testDelivery ? ' (тестовое отправление, посылка не будет отправлена)' : '' ?>.<br>
                                <?= $testDelivery ? 'Тестовый трек не предназначен для реальной доставки.' : 'После регистрации посылки отслеживать заказ можно на сайте:' ?>
                                <a href="<?= $trackingUrl ?>" style="color: #111111; font-weight: 500;"><?= $carrier ?></a>
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
