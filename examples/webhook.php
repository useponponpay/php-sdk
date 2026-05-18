<?php
/**
 * PolyPay PHP SDK - webhook callback example
 *
 * Deploy this file to your server and use its URL as the order notify_url value.
 * PolyPay will send POST requests to this URL whenever the order status changes.
 */

// Load via Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Load without Composer
require_once __DIR__ . '/../autoload.php';

use PolyPay\PolyPay;
use PolyPay\WebhookHandler;
use PolyPay\Exception\SignatureException;

// ==================== Configuration ====================

$apiKey = 'YOUR_API_KEY_HERE';

// ==================== Handle Callback ====================

try {
    $polypay = new PolyPay($apiKey);

    // Verify the signature and get the callback payload.
    $data = $polypay->webhook()->handle();

    // Get the normalized status.
    $status = WebhookHandler::resolveStatus($data);
    $orderNo = $data['order_no'] ?? '';

    // Log the callback.
    error_log("[PolyPay Webhook] Order: {$orderNo}, Status: {$status}");

    switch ($status) {
        case 'paid':
            // Payment succeeded. Update your business order status.
            // updateOrderStatus($orderNo, 'paid');
            // sendConfirmationEmail($orderNo);
            error_log("[PolyPay] Payment success: {$orderNo}, TX: " . ($data['tx_hash'] ?? ''));
            break;

        case 'expired':
            // The order expired.
            // updateOrderStatus($orderNo, 'expired');
            error_log("[PolyPay] Payment expired: {$orderNo}");
            break;

        case 'cancelled':
            // The order was cancelled.
            // updateOrderStatus($orderNo, 'cancelled');
            error_log("[PolyPay] Payment cancelled: {$orderNo}");
            break;

        case 'pending':
            // Waiting for payment. Usually no action is needed.
            break;

        default:
            error_log("[PolyPay] Unknown status: " . ($data['status'] ?? 'null'));
    }

    // You must return 200 + "OK" to tell PolyPay the callback was handled.
    http_response_code(200);
    echo 'OK';

} catch (SignatureException $e) {
    // Signature verification failed.
    error_log("[PolyPay Webhook] Signature error: {$e->getMessage()}");
    http_response_code($e->getHttpStatus());
    echo $e->getMessage();

} catch (\Exception $e) {
    // Other errors.
    error_log("[PolyPay Webhook] Error: {$e->getMessage()}");
    http_response_code(500);
    echo 'Internal error';
}
