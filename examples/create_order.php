<?php
/**
 * PonponPay PHP SDK - create order example
 *
 * Demonstrates how to use the SDK to create a cryptocurrency payment order.
 */

// Load via Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Load without Composer
require_once __DIR__ . '/../autoload.php';

use PonponPay\PonponPay;
use PonponPay\Exception\ApiException;
use PonponPay\Exception\ConfigException;

// ==================== Configuration ====================

$apiKey = 'YOUR_API_KEY_HERE';  // Replace with your API Key

// ==================== Create Order ====================

try {
    $ponponpay = new PonponPay($apiKey);

    // Create an order
    $order = $ponponpay->createOrder([
        'mch_order_id' => 'ORDER_' . time(),           // Merchant order ID
        'currency'     => 'USDT',                       // Currency
        'network'      => 'tron',                       // Network
        'amount'       => 10.00,                        // Amount
        'notify_url'   => 'https://your-site.com/webhook.php',  // Callback URL
        'redirect_url' => 'https://your-site.com/success',      // Redirect URL
    ]);

    echo "Order created successfully!\n";
    echo "Trade ID:    {$order->tradeId}\n";
    echo "Payment URL: {$order->paymentUrl}\n";
    echo "Amount:      {$order->amount}\n";
    echo "Address:     {$order->address}\n";
    echo "Expires At:  " . date('Y-m-d H:i:s', $order->expiresAt) . "\n";

    // Return payment_url to the frontend so the user can be redirected to pay.
    // header('Location: ' . $order->paymentUrl);

} catch (ConfigException $e) {
    echo "Configuration error: {$e->getMessage()}\n";
} catch (ApiException $e) {
    echo "API error: {$e->getMessage()}\n";
    echo "HTTP Code: {$e->getHttpCode()}\n";
    echo "API Code:  {$e->getApiCode()}\n";
}
