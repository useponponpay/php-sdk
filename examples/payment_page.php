<?php
/**
 * PolyPay PHP SDK - hosted payment page example
 *
 * This file intentionally redirects to PolyPay hosted checkout instead of
 * rendering a merchant-side payment method selection page.
 */

require_once __DIR__ . '/../autoload.php';

use PolyPay\PolyPay;
use PolyPay\Exception\ApiException;

$publicKey = 'pub_your_public_key';
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 10.00;
$orderId = isset($_GET['order_id']) ? (string)$_GET['order_id'] : 'ORDER_' . time();

try {
    $params = [
        'public_key' => $publicKey,
        'amount' => $amount,
        'order_id' => $orderId,
        'notify_url' => 'https://your-site.com/webhook.php',
        'redirect_url' => 'https://your-site.com/success',
    ];

    if (!empty($_GET['currency']) && !empty($_GET['network'])) {
        $params['currency'] = (string)$_GET['currency'];
        $params['network'] = (string)$_GET['network'];
    }

    header('Location: ' . PolyPay::buildCheckoutUrl($params));
    exit;
} catch (ApiException $e) {
    http_response_code(400);
    echo 'Checkout error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
