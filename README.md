# PolyPay PHP SDK

Accept cryptocurrency payments (USDT, USDC, etc.) on any PHP website via [PolyPay](https://polypay.ai).

[English](#installation) | [中文](#安装)

## Features

- 🔑 **Simple Setup** — Just provide your API Key
- 🌐 **Framework Agnostic** — Works with any PHP project (Laravel, WordPress, custom, etc.)
- 📦 **Zero Dependencies** — Pure PHP with cURL, no external packages required
- 🔒 **Webhook Verification** — Recommended Ed25519/JWKS v2 verification plus legacy HMAC v1 compatibility
- 🤖 **Agent Payments** — x402 helper for API/resource payments by agents
- 💰 **Multi-Currency** — Support USDT, USDC on Tron, Ethereum, BSC, Polygon, Solana

## Requirements

- PHP >= 7.4
- ext-curl
- ext-json

## Installation

### Via Composer (Recommended)

```bash
composer require polypay/php-sdk
```

### Manual Installation

Download the SDK and include the autoloader:

```php
require_once '/path/to/php-sdk/autoload.php';
```

## Quick Start

### 1. Initialize

```php
use PolyPay\PolyPay;

$polypay = new PolyPay('your-api-key');
```

### Testing

Use a production API key with a low-risk order amount and a dedicated webhook endpoint to validate order creation and callback handling before sending real traffic.

### 2. Redirect to Hosted Checkout with API Key Mode

```php
$checkoutUrl = $polypay->createCheckoutUrl([
    'mch_order_id' => 'ORDER_001',
    'amount'       => 10.00,
    'notify_url'   => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
    'locale'       => 'en',
]);

header('Location: ' . $checkoutUrl);
exit;
```

PolyPay returns a signed hosted checkout URL such as:

```text
https://checkout.polypay.ai/en/checkout
```

PolyPay displays the payment method selection page. If your site already has a confirmed payment method, pass `currency` and `network` to skip selection and go directly to the payment page:

```php
$checkoutUrl = $polypay->createCheckoutUrl([
    'mch_order_id' => 'ORDER_001',
    'amount'       => 10.00,
    'notify_url'   => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
    'locale'       => 'en',
    'currency'     => 'USDT',
    'network'      => 'Tron',
]);
```

Use `PolyPay::buildCheckoutUrl()` only when you already manage Public Key hosted checkout parameters yourself:

```php
$checkoutUrl = PolyPay::buildCheckoutUrl([
    'public_key'   => 'pub_your_public_key',
    'amount'       => 10.00,
    'order_id'     => 'ORDER_001',
    'notify_url'   => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
    'currency'     => 'USDT',
    'network'      => 'Tron',
]);
```

### 3. Create an Order with API Key Mode

```php
$order = $polypay->createOrder([
    'mch_order_id' => 'ORDER_001',
    'currency'     => 'USDT',
    'network'      => 'tron',
    'amount'       => 10.00,
    'notify_url'   => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
]);

echo $order->paymentUrl;  // Redirect user to this URL
echo $order->tradeId;     // PolyPay trade ID
echo $order->address;     // Payment address
```

For normal merchant checkout, prefer hosted checkout so PolyPay owns payment method selection.

### 4. Query Order

```php
// By trade ID
$order = $polypay->getOrderByTradeId('T20240101120000123456');

// By merchant order ID
$order = $polypay->getOrderByMchOrderId('ORDER_001');

echo $order->status;   // paid, pending, expired, cancelled
echo $order->txHash;   // Blockchain transaction hash
```

### 5. Handle Webhook Callback

New integrations should use strict v2 verification. It validates PolyPay's Ed25519 signature, the signed merchant/environment audience, timestamp, and nonce without choosing an API Key.

```php
try {
    $data = $polypay->webhookV2('MCH_YOUR_ID', 'production')->handle();
    $status = WebhookHandler::resolveStatus($data);

    if ($status === 'paid') {
        // Payment successful!
        // Update your order status here
    }

    http_response_code(200);
    echo 'OK';
} catch (\PolyPay\Exception\SignatureException $e) {
    http_response_code($e->getHttpStatus());
    echo $e->getMessage();
}
```

Existing integrations may continue using `$polypay->webhook()->handle()` for API Key HMAC v1. PolyPay sends both signatures during migration. A v2 verification failure never falls back to v1.

### 6. Protect an API with x402 Agent Payments

```php
$x402 = $polypay->x402([
    'resource' => [
        'payTo' => '0xYourMerchantSettlementWallet',
        'resource' => 'https://api.example.com/premium-data',
        'method' => 'GET',
        'price' => '$0.01',
        'amount' => '10000',
        'network' => 'eip155:8453',
        'asset' => 'USDC',
        'description' => 'Premium market data',
    ],
]);

$result = $x402->verifyAndSettle();

if (!$result['paid']) {
    $x402->sendRequirementAndExit($result['required']);
}

header('Content-Type: application/json');
foreach ($result['responseHeaders'] as $name => $value) {
    header($name . ': ' . $value);
}
echo json_encode(['data' => 'premium payload']);
```

The helper emits x402 v2 by default. Set top-level `protocolVersion => 1` only during a controlled migration for legacy clients.
The configured `resource` URL and `method` are used as the canonical public request identity behind reverse proxies. Pass an explicit URL to `verifyAndSettle()` only when the route resolves its canonical URL dynamically. A v2 helper reads only `PAYMENT-SIGNATURE`; a v1 helper reads only `X-PAYMENT`.

Keep the matching Dashboard Resource enabled. Settlement rejects disabled or missing resources. Raw standard facilitator requests that omit method/resource context are accepted only when the enabled Resource resolves uniquely from merchant, network, asset, amount, and recipient.

`paid` reports settlement state. `shouldFulfill` is true only for the request that wins the first confirmed payment-state transition; concurrent or later successful requests receive false. `fulfillmentKey` is the stable PolyPay payment ID. This flag is not a replacement for business idempotency: stateful endpoints must atomically persist their first business response under the unique key and return that stored response on retries. Read-only endpoints may continue serving the same protected representation when `paid` is true.

## API Reference

### `PolyPay` Class

| Method | Description | Returns |
|--------|-------------|---------|
| `createCheckoutUrl(array $params)` | Request a signed hosted checkout URL with API Key Mode | `string` |
| `buildCheckoutUrl(array $params, array $options = [])` | Build a Public Key signed hosted checkout URL manually | `string` |
| `generateCheckoutSignature(array $params, string $publicKey)` | Generate hosted checkout signature | `string` |
| `getPaymentMethods()` | Get available payment methods | `PaymentMethod[]` |
| `createOrder(array $params)` | Create a payment order | `Order` |
| `getOrderByTradeId(string $tradeId)` | Query order by trade ID | `Order` |
| `getOrderByMchOrderId(string $mchOrderId)` | Query order by merchant order ID | `Order` |
| `getMerchantDetail()` | Get merchant info | `Merchant` |
| `activatePlugin(string $type)` | Activate plugin | `bool` |
| `webhookV2(string $merchantId, string $environment, ?NonceStorageInterface $nonce, ?WebhookPublicKeyProviderInterface $keys)` | Create the recommended strict Ed25519/JWKS verifier | `WebhookV2Handler` |
| `webhook(?NonceStorageInterface $nonce)` | Create the legacy API Key HMAC v1 verifier | `WebhookHandler` |
| `x402(array $options)` | Create x402 agent payment helper | `X402` |

### `x402` Resource Options

| Parameter | Required | Description |
|-----------|----------|-------------|
| `payTo` | ✅ | Merchant EVM wallet address receiving USDC |
| `resource` | ✅ | Canonical protected resource URL |
| `price` | ✅* | Human-readable price, e.g. `$0.01` |
| `amount` | ✅* | x402 v2 USDC base-unit amount; required if `price` is omitted |
| `maxAmountRequired` | ❌ | Legacy v1 alias for `amount` during migration |
| `method` | ❌ | Protected HTTP method |
| `description` | ❌ | Description shown to agents |
| `mimeType` | ❌ | Resource MIME type |
| `scheme` | ❌ | Defaults to `exact` |
| `network` | ❌ | Defaults to `eip155:8453`; supported: `eip155:8453`, `eip155:1`, `eip155:137` |
| `asset` | ❌ | Defaults to `USDC` |
| `assetContract` | ❌ | Defaults to the network-specific Circle USDC contract |
| `maxTimeoutSeconds` | ❌ | Defaults to `60` |
| top-level `protocolVersion` | ❌ | Defaults to `2`; use `1` only for an explicit legacy migration |

> x402 verification binds the payment to `resource` and `method`. The configured public URL is used by default behind a proxy; pass the canonical URL to `verifyAndSettle($headers, $method, $url)` only for dynamically resolved routes.

Supported standard x402 networks:

| Network | Chain | USDC Contract |
|---------|-------|---------------|
| `eip155:8453` | Base | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` |
| `eip155:1` | Ethereum | `0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48` |
| `eip155:137` | Polygon | `0x3c499c542cef5e3811e1192ce70d8cc03d5c3359` |

Only standard EVM `exact` payments with Circle USDC `transferWithAuthorization` are supported. BSC, Tron, Solana, TON, and BTC are not part of this standard exact flow.

The helper validates the scheme, network, matching Circle USDC contract, and EIP-3009 metadata during construction. Unsupported overrides fail before a payment challenge is sent to a wallet. If a settle request times out, retry the same signed payment proof; do not create a replacement authorization solely because the result is indeterminate.

### `createOrder` Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `mch_order_id` | ✅ | Your unique order ID |
| `currency` | ✅ | Cryptocurrency: `USDT`, `USDC` |
| `network` | ✅ | Network: `tron`, `ethereum`, `bsc`, `polygon`, `solana` |
| `amount` | ✅ | Payment amount (in fiat currency) |
| `notify_url` | ✅ | Webhook callback URL |
| `redirect_url` | ❌ | URL to redirect after payment |

### `Order` Model Properties

| Property | Type | Description |
|----------|------|-------------|
| `tradeId` | `string` | PolyPay trade ID |
| `paymentUrl` | `string` | Payment page URL |
| `amount` | `float` | Order amount |
| `actualAmount` | `float` | Actual crypto amount |
| `address` | `string` | Payment address |
| `expiresAt` | `?int` | Expiry timestamp (Unix) |
| `currency` | `string` | Cryptocurrency |
| `network` | `string` | Network |
| `status` | `string` | Order status |
| `txHash` | `string` | Transaction hash |
| `mchOrderId` | `string` | Merchant order ID |

### Webhook Callback Status Codes

| Status Code | Meaning | Resolved Status |
|-------------|---------|-----------------|
| 1 | Pending payment | `pending` |
| 2 | Payment successful | `paid` |
| 3 | Expired | `expired` |
| 4 | Cancelled | `cancelled` |
| 5 | Manual recharge | `paid` |
| 6 | Confirming on-chain | `pending` |

## Configuration Options

```php
$polypay = new PolyPay('your-api-key', [
    'api_url'        => 'https://api.polypay.ai',  // API base URL
    'timeout'        => 30,                            // Request timeout (seconds)
    'debug'          => false,                         // Enable debug logging
    'debug_log_file' => '/tmp/polypay-debug.log',    // Debug log file path
]);
```

## Custom Nonce Storage

By default, the webhook handler uses file-based nonce storage. For high-traffic scenarios, implement `NonceStorageInterface` with Redis:

```php
use PolyPay\Nonce\NonceStorageInterface;

class RedisNonceStorage implements NonceStorageInterface
{
    private $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function consume(string $nonce, int $ttl = 600): bool
    {
        // SET NX returns true only if key doesn't exist
        return $this->redis->set('polypay_nonce:' . $nonce, '1', ['NX', 'EX' => $ttl]);
    }
}

// Usage
$handler = $polypay->webhook(new RedisNonceStorage($redis));
```

## Error Handling

```php
use PolyPay\Exception\ConfigException;
use PolyPay\Exception\ApiException;
use PolyPay\Exception\SignatureException;

try {
    $order = $polypay->createOrder([...]);
} catch (ConfigException $e) {
    // API Key not configured
} catch (ApiException $e) {
    echo $e->getMessage();       // Error message
    echo $e->getHttpCode();      // HTTP status code
    echo $e->getApiCode();       // Business error code
    echo $e->getResponseBody();  // Raw response body
}
```

## Examples

See the [`examples/`](./examples) directory for complete, runnable examples:

- [`create_order.php`](./examples/create_order.php) — Create a payment order
- [`query_order.php`](./examples/query_order.php) — Query order status
- [`webhook.php`](./examples/webhook.php) — Handle payment callback
- [`payment_methods.php`](./examples/payment_methods.php) — List available methods
- [`hosted_checkout.php`](./examples/hosted_checkout.php) — Redirect to hosted checkout
- [`payment_page.php`](./examples/payment_page.php) — Hosted checkout redirect example

## License

MIT License. See [LICENSE](./LICENSE) for details.

---

# 安装

### 通过 Composer（推荐）

```bash
composer require polypay/php-sdk
```

### 手动安装

下载 SDK 并引入自动加载文件：

```php
require_once '/path/to/php-sdk/autoload.php';
```

## 快速开始

```php
use PolyPay\PolyPay;
use PolyPay\WebhookHandler;

// 初始化
$polypay = new PolyPay('你的API Key');

// 使用 API Key 模式跳转到 PolyPay 托管收银台，由 PolyPay 统一选择支付方式
$checkoutUrl = $polypay->createCheckoutUrl([
    'mch_order_id' => 'ORDER_001',
    'amount'       => 10.00,
    'notify_url'   => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
    'locale'       => 'zh',
]);

header('Location: ' . $checkoutUrl);
exit;

// 默认跳转页面：https://checkout.polypay.ai/en/checkout
// 中文收银台：locale = zh，对应页面：https://checkout.polypay.ai/zh/checkout

// 如果商户已经明确支付方式，也可以使用 API Key 模式直接创建订单
$order = $polypay->createOrder([
    'mch_order_id' => 'ORDER_001',
    'currency'     => 'USDT',
    'network'      => 'tron',
    'amount'       => 10.00,
    'notify_url'   => 'https://your-site.com/webhook.php',
]);

// 处理回调（自动共享 API Key）
$data = $polypay->webhook()->handle();
if (WebhookHandler::resolveStatus($data) === 'paid') {
    // 支付成功，更新订单状态
}

// x402 Agent 支付保护接口
$x402 = $polypay->x402([
    'resource' => [
        'payTo' => '0x你的EVM收款钱包',
        'resource' => 'https://api.example.com/premium-data',
        'method' => 'GET',
        'price' => '$0.01',
        'amount' => '10000',
        'network' => 'eip155:8453',
        'asset' => 'USDC',
        'description' => '高级数据接口',
    ],
]);

$result = $x402->verifyAndSettle();
if (!$result['paid']) {
    $x402->sendRequirementAndExit($result['required']);
}
```

更多示例请参考 [`examples/`](./examples) 目录。
