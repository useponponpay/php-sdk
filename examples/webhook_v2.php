<?php
/**
 * Recommended PolyPay Webhook v2 callback example.
 */

require_once __DIR__ . '/../autoload.php';

use PolyPay\Exception\SignatureException;
use PolyPay\PolyPay;
use PolyPay\WebhookHandler;

$polypay = new PolyPay(getenv('POLYPAY_API_KEY') ?: 'unused-by-webhook-v2');

try {
    $data = $polypay->webhookV2(
        getenv('POLYPAY_MERCHANT_ID') ?: '',
        getenv('POLYPAY_ENVIRONMENT') ?: 'production'
    )->handle();

    if (WebhookHandler::resolveStatus($data) === 'paid') {
        // Update the business order idempotently using event_id.
    }
    http_response_code(200);
    echo 'OK';
} catch (SignatureException $error) {
    http_response_code($error->getHttpStatus());
    echo $error->getMessage();
}
