<?php
/**
 * PolyPay PHP SDK - payment methods example
 *
 * Demonstrates how to fetch the list of payment methods available to the merchant.
 */

// Load via Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Load without Composer
require_once __DIR__ . '/../autoload.php';

use PolyPay\PolyPay;
use PolyPay\Exception\ApiException;

// ==================== Configuration ====================

$apiKey = 'YOUR_API_KEY_HERE';

// ==================== Get Payment Methods ====================

try {
    $polypay = new PolyPay($apiKey);

    $methods = $polypay->getPaymentMethods();

    echo "Available payment methods:\n";
    echo str_repeat('-', 40) . "\n";

    foreach ($methods as $method) {
        echo "Network: {$method->network}\n";
        echo "  Currencies: " . implode(', ', $method->currencies) . "\n";
    }

} catch (ApiException $e) {
    echo "API error: {$e->getMessage()}\n";
}
