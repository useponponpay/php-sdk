<?php
/**
 * PonponPay PHP SDK - webhook callback example
 *
 * Deploy this file to your server and use its URL as the order notify_url value.
 * PonponPay will send POST requests to this URL whenever the order status changes.
 */

// Load via Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Load without Composer
require_once __DIR__ . '/../autoload.php';

use PonponPay\PonponPay;
use PonponPay\WebhookHandler;
use PonponPay\Exception\SignatureException;

// ==================== Configuration ====================

$apiKey = 'YOUR_API_KEY_HERE';

// ==================== Handle Callback ====================

try {
    $ponponpay = new PonponPay($apiKey);

    // Verify the signature and get the callback payload.
    $data = $ponponpay->webhook()->handle();

    // Get the normalized status.
    $status = WebhookHandler::resolveStatus($data);
    $orderNo = $data['order_no'] ?? '';

    // Log the callback.
    error_log("[PonponPay Webhook] Order: {$orderNo}, Status: {$status}");

    switch ($status) {
        case 'paid':
            // Payment succeeded. Update your business order status.
            // updateOrderStatus($orderNo, 'paid');
            // sendConfirmationEmail($orderNo);
            error_log("[PonponPay] Payment success: {$orderNo}, TX: " . ($data['tx_hash'] ?? ''));
            break;

        case 'expired':
            // The order expired.
            // updateOrderStatus($orderNo, 'expired');
            error_log("[PonponPay] Payment expired: {$orderNo}");
            break;

        case 'cancelled':
            // The order was cancelled.
            // updateOrderStatus($orderNo, 'cancelled');
            error_log("[PonponPay] Payment cancelled: {$orderNo}");
            break;

        case 'pending':
            // Waiting for payment. Usually no action is needed.
            break;

        default:
            error_log("[PonponPay] Unknown status: " . ($data['status'] ?? 'null'));
    }

    // You must return 200 + "OK" to tell PonponPay the callback was handled.
    http_response_code(200);
    echo 'OK';

} catch (SignatureException $e) {
    // Signature verification failed.
    error_log("[PonponPay Webhook] Signature error: {$e->getMessage()}");
    http_response_code($e->getHttpStatus());
    echo $e->getMessage();

} catch (\Exception $e) {
    // Other errors.
    error_log("[PonponPay Webhook] Error: {$e->getMessage()}");
    http_response_code(500);
    echo 'Internal error';
}
