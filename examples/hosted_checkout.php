<?php
/**
 * PolyPay PHP SDK - hosted checkout example
 *
 * Redirects the customer to the PolyPay hosted checkout page. PolyPay handles
 * payment method selection unless currency and network are provided.
 */

// Load via Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Load without Composer
require_once __DIR__ . '/../autoload.php';

use PolyPay\PolyPay;
use PolyPay\Exception\ApiException;

$publicKey = 'pub_your_public_key';

try {
    $checkoutUrl = PolyPay::buildCheckoutUrl([
        'public_key' => $publicKey,
        'amount' => 10.00,
        'order_id' => 'ORDER_' . time(),
        'notify_url' => 'https://your-site.com/webhook.php',
        'redirect_url' => 'https://your-site.com/success',

        // Optional: include both fields to skip payment method selection.
        // 'currency' => 'USDT',
        // 'network' => 'Tron',
    ]);

    header('Location: ' . $checkoutUrl);
    exit;
} catch (ApiException $e) {
    http_response_code(400);
    echo 'Checkout error: ' . $e->getMessage();
}
