<?php
/**
 * Compatibility and protocol tests for Webhook v1 and v2 verification.
 */

require_once __DIR__ . '/../autoload.php';

use PolyPay\Nonce\NonceStorageInterface;
use PolyPay\WebhookHandler;
use PolyPay\WebhookPublicKeyProviderInterface;
use PolyPay\WebhookV2Handler;

/** In-memory replay protection for deterministic tests. */
class WebhookTestNonceStorage implements NonceStorageInterface
{
    /** @var array<string,bool> */
    private array $used = [];

    /** Consume one nonce exactly once. */
    public function consume(string $nonce, int $ttl = 600): bool
    {
        if (isset($this->used[$nonce])) {
            return false;
        }
        $this->used[$nonce] = true;
        return true;
    }
}

/** Fixed raw Ed25519 key provider for tests. */
class WebhookTestKeyProvider implements WebhookPublicKeyProviderInterface
{
    private string $publicKey;

    /** Store one raw Ed25519 public key. */
    public function __construct(string $publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /** Return the configured key. */
    public function getPublicKey(string $keyId): string
    {
        if ($keyId !== 'whk_test_01') {
            throw new RuntimeException('unknown test key');
        }
        return $this->publicKey;
    }
}

$rawBody = '{"event_id":"evt_1001","status":2}';
$timestamp = (string)time();
$nonce = 'AbCdEfGhIjKlMnOp12345678';

// The legacy API Key verifier remains byte-for-byte compatible.
$apiKey = 'test_api_key_123456789';
$legacyPayload = $timestamp . "\n" . $nonce . "\n" . $rawBody;
$legacyHeaders = [
    'X-Key-Prefix' => substr($apiKey, 0, 12),
    'X-Timestamp' => $timestamp,
    'X-Nonce' => $nonce,
    'X-Signature' => hash_hmac('sha256', $legacyPayload, hash('sha256', $apiKey)),
];
$legacyResult = (new WebhookHandler($apiKey, new WebhookTestNonceStorage()))
    ->verify($rawBody, $legacyHeaders);
webhookAssertSame('evt_1001', $legacyResult['event_id'], 'legacy v1 verification');

$keyPair = sodium_crypto_sign_keypair();
$secretKey = sodium_crypto_sign_secretkey($keyPair);
$publicKey = sodium_crypto_sign_publickey($keyPair);
$v2Payload = implode("\n", [
    'polypay-webhook-v2',
    'whk_test_01',
    $timestamp,
    $nonce,
    'MCH1001',
    'production',
    $rawBody,
]);
$signature = sodium_crypto_sign_detached($v2Payload, $secretKey);
$v2Headers = array_merge($legacyHeaders, [
    'X-Webhook-Signature-Version' => 'v2',
    'X-Webhook-Key-Id' => 'whk_test_01',
    'X-Webhook-Merchant-Id' => 'MCH1001',
    'X-Webhook-Environment' => 'production',
    'X-Webhook-Signature-V2' => rtrim(strtr(base64_encode($signature), '+/', '-_'), '='),
]);
$handler = new WebhookV2Handler(
    'MCH1001',
    'production',
    new WebhookTestNonceStorage(),
    new WebhookTestKeyProvider($publicKey)
);
$v2Result = $handler->verify($rawBody, $v2Headers);
webhookAssertSame(2, $v2Result['status'], 'v2 verification');

webhookAssertThrows(
    static function () use ($handler, $rawBody, $v2Headers): void {
        $handler->verify($rawBody, $v2Headers);
    },
    'Nonce already used',
    'v2 verifier rejects a replay'
);

$invalidSignatureHeaders = $v2Headers;
$invalidSignatureHeaders['X-Webhook-Signature-V2'] = str_repeat('A', 86);
webhookAssertThrows(
    static function () use ($rawBody, $invalidSignatureHeaders, $publicKey): void {
        (new WebhookV2Handler(
            'MCH1001',
            'production',
            new WebhookTestNonceStorage(),
            new WebhookTestKeyProvider($publicKey)
        ))->verify($rawBody, $invalidSignatureHeaders);
    },
    'Invalid Webhook v2 signature',
    'v2 verifier rejects a wrong signature'
);

$expiredHeaders = $v2Headers;
$expiredHeaders['X-Timestamp'] = (string)(time() - 301);
webhookAssertThrows(
    static function () use ($rawBody, $expiredHeaders, $publicKey): void {
        (new WebhookV2Handler(
            'MCH1001',
            'production',
            new WebhookTestNonceStorage(),
            new WebhookTestKeyProvider($publicKey)
        ))->verify($rawBody, $expiredHeaders);
    },
    'Timestamp expired',
    'v2 verifier rejects an expired timestamp'
);

webhookAssertThrows(
    static function () use ($handler, $rawBody, $legacyHeaders): void {
        $handler->verify($rawBody, $legacyHeaders);
    },
    'Unsupported webhook signature version',
    'v2 verifier never falls back to v1'
);
webhookAssertThrows(
    static function () use ($rawBody, $v2Headers, $publicKey): void {
        (new WebhookV2Handler(
            'MCH9999',
            'production',
            new WebhookTestNonceStorage(),
            new WebhookTestKeyProvider($publicKey)
        ))->verify($rawBody, $v2Headers);
    },
    'Invalid webhook audience',
    'v2 verifier binds the merchant audience'
);

echo "webhook v1/v2 protocol tests passed\n";

/** Assert strict equality without a test framework. */
function webhookAssertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

/** Assert an exception contains an expected message. */
function webhookAssertThrows(callable $callback, string $expectedMessage, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        if (strpos($error->getMessage(), $expectedMessage) !== false) {
            return;
        }
        throw new RuntimeException($message . ': unexpected error ' . $error->getMessage());
    }
    throw new RuntimeException($message . ': expected exception was not thrown');
}
