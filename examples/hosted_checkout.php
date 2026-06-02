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
use PolyPay\Exception\ConfigException;

$apiKey = 'YOUR_API_KEY_HERE';

try {
    $polypay = new PolyPay($apiKey);

    $checkoutUrl = $polypay->createCheckoutUrl([
        'mch_order_id' => 'ORDER_' . time(),
        'amount' => 10.00,
        'notify_url' => 'https://your-site.com/webhook.php',
        'redirect_url' => 'https://your-site.com/success',
        'locale' => 'en',

        // Optional: include both fields to skip payment method selection.
        // 'currency' => 'USDT',
        // 'network' => 'Tron',
    ]);

    header('Location: ' . $checkoutUrl);
    exit;
} catch (ConfigException $e) {
    http_response_code(400);
    echo 'Configuration error: ' . $e->getMessage();
} catch (ApiException $e) {
    http_response_code(400);
    echo 'Checkout error: ' . $e->getMessage();
}
