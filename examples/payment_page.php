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
use PolyPay\Exception\ConfigException;

$apiKey = 'YOUR_API_KEY_HERE';
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 10.00;
$orderId = isset($_GET['order_id']) ? (string)$_GET['order_id'] : 'ORDER_' . time();

try {
    $polypay = new PolyPay($apiKey);

    $params = [
        'mch_order_id' => $orderId,
        'amount' => $amount,
        'notify_url' => 'https://your-site.com/webhook.php',
        'redirect_url' => 'https://your-site.com/success',
        'locale' => 'en',
    ];

    if (!empty($_GET['currency']) && !empty($_GET['network'])) {
        $params['currency'] = (string)$_GET['currency'];
        $params['network'] = (string)$_GET['network'];
    }

    header('Location: ' . $polypay->createCheckoutUrl($params));
    exit;
} catch (ConfigException $e) {
    http_response_code(400);
    echo 'Configuration error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} catch (ApiException $e) {
    http_response_code(400);
    echo 'Checkout error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
