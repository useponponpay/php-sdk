<?php
/**
 * PonponPay PHP SDK - query order example
 *
 * Demonstrates how to query an order that has already been created.
 */

// Load via Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Load without Composer
require_once __DIR__ . '/../autoload.php';

use PonponPay\PonponPay;
use PonponPay\Exception\ApiException;

// ==================== Configuration ====================

$apiKey = 'YOUR_API_KEY_HERE';

// ==================== Query Order ====================

try {
    $ponponpay = new PonponPay($apiKey);

    // Option 1: query by trade ID
    $order = $ponponpay->getOrderByTradeId('T20240101120000123456');

    echo "Order found!\n";
    echo "Trade ID:      {$order->tradeId}\n";
    echo "Mch Order ID:  {$order->mchOrderId}\n";
    echo "Status:        {$order->status}\n";
    echo "Amount:        {$order->amount}\n";
    echo "Currency:      {$order->currency}\n";
    echo "Network:       {$order->network}\n";
    echo "TX Hash:       {$order->txHash}\n";

    // Option 2: query by merchant order ID
    // $order = $ponponpay->getOrderByMchOrderId('ORDER_12345');

} catch (ApiException $e) {
    echo "API error: {$e->getMessage()}\n";
}
