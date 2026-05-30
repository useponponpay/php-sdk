# PolyPay PHP SDK

Accept cryptocurrency payments (USDT, USDC, etc.) on any PHP website via [PolyPay](https://polypay.ai).

[English](#installation) | [中文](#安装)

## Features

- 🔑 **Simple Setup** — Just provide your API Key
- 🌐 **Framework Agnostic** — Works with any PHP project (Laravel, WordPress, custom, etc.)
- 📦 **Zero Dependencies** — Pure PHP with cURL, no external packages required
- 🔒 **Webhook Verification** — Built-in HMAC-SHA256 signature validation with replay protection
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

### Sandbox Testing

Use a sandbox API key that starts with `sk_sandbox_` to create sandbox orders:

```php
$polypay = new PolyPay('sk_sandbox_your_key');
```

The API base URL stays the same. PolyPay separates production and sandbox data by the API key environment. Sandbox orders use `SB...` trade IDs and virtual payment addresses, and you can simulate payment states from the merchant dashboard.

### 2. Redirect to Hosted Checkout

```php
$checkoutUrl = PolyPay::buildCheckoutUrl([
    'public_key'   => 'pub_your_public_key',
    'amount'       => 10.00,
    'order_id'     => 'ORDER_001',
    'notify_url'   => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
]);

header('Location: ' . $checkoutUrl);
exit;
```

PolyPay displays the payment method selection page. If your site already has a confirmed payment method, pass `currency` and `network` to skip selection and go directly to the payment page:

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

```php
try {
    $data = $polypay->webhook()->handle();
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

### 6. Protect an API with x402 Agent Payments

```php
$x402 = $polypay->x402([
    'resource' => [
        'payTo' => '0xYourMerchantSettlementWallet',
        'resource' => 'https://api.example.com/premium-data',
        'method' => 'GET',
        'price' => '$0.01',
        'maxAmountRequired' => '10000',
        'network' => 'eip155:8453',
        'asset' => 'USDC',
        'description' => 'Premium market data',
    ],
]);

$result = $x402->verifyAndSettle();

if (!$result['paid']) {
    $x402->sendRequirementAndExit();
}

header('Content-Type: application/json');
echo json_encode(['data' => 'premium payload']);
```

## API Reference

### `PolyPay` Class

| Method | Description | Returns |
|--------|-------------|---------|
| `buildCheckoutUrl(array $params, array $options = [])` | Build hosted checkout URL | `string` |
| `generateCheckoutSignature(array $params, string $publicKey)` | Generate hosted checkout signature | `string` |
| `getPaymentMethods()` | Get available payment methods | `PaymentMethod[]` |
| `createOrder(array $params)` | Create a payment order | `Order` |
| `getOrderByTradeId(string $tradeId)` | Query order by trade ID | `Order` |
| `getOrderByMchOrderId(string $mchOrderId)` | Query order by merchant order ID | `Order` |
| `getMerchantDetail()` | Get merchant info | `Merchant` |
| `activatePlugin(string $type)` | Activate plugin | `bool` |
| `webhook(?NonceStorageInterface $nonce)` | Create webhook handler (shares API Key) | `WebhookHandler` |
| `x402(array $options)` | Create x402 agent payment helper | `X402` |

### `x402` Resource Options

| Parameter | Required | Description |
|-----------|----------|-------------|
| `payTo` | ✅ | Merchant EVM wallet address receiving USDC |
| `resource` | ✅ | Canonical protected resource URL |
| `price` | ✅* | Human-readable price, e.g. `$0.01` |
| `maxAmountRequired` | ✅* | USDC base-unit amount; required if `price` is omitted |
| `method` | ❌ | Protected HTTP method |
| `description` | ❌ | Description shown to agents |
| `mimeType` | ❌ | Resource MIME type |
| `scheme` | ❌ | Defaults to `exact` |
| `network` | ❌ | Defaults to `eip155:8453`; supported: `eip155:8453`, `eip155:1`, `eip155:137` |
| `asset` | ❌ | Defaults to `USDC` |
| `assetContract` | ❌ | Defaults to the network-specific Circle USDC contract |
| `maxTimeoutSeconds` | ❌ | Defaults to `60` |

> x402 verification binds the payment to `resource` and `method`. If your application is behind a proxy, pass the canonical public URL to `verifyAndSettle($headers, $method, $url)` so it matches the URL advertised in the 402 requirement.

Supported standard x402 networks:

| Network | Chain | USDC Contract |
|---------|-------|---------------|
| `eip155:8453` | Base | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` |
| `eip155:1` | Ethereum | `0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48` |
| `eip155:137` | Polygon | `0x3c499c542cef5e3811e1192ce70d8cc03d5c3359` |

Only standard EVM `exact` payments with Circle USDC `transferWithAuthorization` are supported. BSC, Tron, Solana, TON, and BTC are not part of this standard exact flow.

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

// 跳转到 PolyPay 托管收银台，由 PolyPay 统一选择支付方式
$checkoutUrl = PolyPay::buildCheckoutUrl([
    'public_key'   => 'pub_your_public_key',
    'amount'       => 10.00,
    'order_id'     => 'ORDER_001',
    'notify_url'   => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
]);

header('Location: ' . $checkoutUrl);
exit;

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
        'maxAmountRequired' => '10000',
        'network' => 'eip155:8453',
        'asset' => 'USDC',
        'description' => '高级数据接口',
    ],
]);

$result = $x402->verifyAndSettle();
if (!$result['paid']) {
    $x402->sendRequirementAndExit();
}
```

更多示例请参考 [`examples/`](./examples) 目录。
