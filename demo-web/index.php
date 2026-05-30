<?php
/**
 * PolyPay PHP SDK demo web entry.
 *
 * Redirects to PolyPay hosted checkout. PolyPay owns payment method selection;
 * pass currency and network only when the merchant already selected a method.
 */

require_once __DIR__ . '/../autoload.php';

use PolyPay\PolyPay;
use PolyPay\Exception\ApiException;

$publicKey = 'pub_your_public_key';

$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 10.00;
$params = [
    'public_key' => $publicKey,
    'amount' => $amount,
    'order_id' => $_GET['order_id'] ?? ('DEMO_' . time()),
    'notify_url' => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
];

if (!empty($_GET['currency']) && !empty($_GET['network'])) {
    $params['currency'] = (string)$_GET['currency'];
    $params['network'] = (string)$_GET['network'];
}

try {
    header('Location: ' . PolyPay::buildCheckoutUrl($params));
    exit;
} catch (ApiException $e) {
    http_response_code(400);
    echo 'Checkout error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
