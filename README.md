# PonponPay PHP SDK

Accept cryptocurrency payments (USDT, USDC, etc.) on any PHP website via [PonponPay](https://ponponpay.com).

[English](#installation) | [中文](#安装)

## Features

- 🔑 **Simple Setup** — Just provide your API Key
- 🌐 **Framework Agnostic** — Works with any PHP project (Laravel, WordPress, custom, etc.)
- 📦 **Zero Dependencies** — Pure PHP with cURL, no external packages required
- 🔒 **Webhook Verification** — Built-in HMAC-SHA256 signature validation with replay protection
- 💰 **Multi-Currency** — Support USDT, USDC on Tron, Ethereum, BSC, Polygon, Solana

## Requirements

- PHP >= 7.4
- ext-curl
- ext-json

## Installation

### Via Composer (Recommended)

```bash
composer require ponponpay/php-sdk
```

### Manual Installation

Download the SDK and include the autoloader:

```php
require_once '/path/to/php-sdk/autoload.php';
```

## Quick Start

### 1. Initialize

```php
use PonponPay\PonponPay;

$ponponpay = new PonponPay('your-api-key');
```

### 2. Get Payment Methods

```php
$methods = $ponponpay->getPaymentMethods();

foreach ($methods as $method) {
    echo $method->network . ': ' . implode(', ', $method->currencies) . "\n";
}
// Output:
// Tron: USDT
// Ethereum: USDT, USDC
// BSC: USDT, USDC
```

### 3. Create an Order

```php
$order = $ponponpay->createOrder([
    'mch_order_id' => 'ORDER_001',
    'currency'     => 'USDT',
    'network'      => 'tron',
    'amount'       => 10.00,
    'notify_url'   => 'https://your-site.com/webhook.php',
    'redirect_url' => 'https://your-site.com/success',
]);

echo $order->paymentUrl;  // Redirect user to this URL
echo $order->tradeId;     // PonponPay trade ID
echo $order->address;     // Payment address
```

### 4. Query Order

```php
// By trade ID
$order = $ponponpay->getOrderByTradeId('T20240101120000123456');

// By merchant order ID
$order = $ponponpay->getOrderByMchOrderId('ORDER_001');

echo $order->status;   // paid, pending, expired, cancelled
echo $order->txHash;   // Blockchain transaction hash
```

### 5. Handle Webhook Callback

```php
try {
    $data = $ponponpay->webhook()->handle();
    $status = WebhookHandler::resolveStatus($data);

    if ($status === 'paid') {
        // Payment successful!
        // Update your order status here
    }

    http_response_code(200);
    echo 'OK';
} catch (\PonponPay\Exception\SignatureException $e) {
    http_response_code($e->getHttpStatus());
    echo $e->getMessage();
}
```

## API Reference

### `PonponPay` Class

| Method | Description | Returns |
|--------|-------------|---------|
| `getPaymentMethods()` | Get available payment methods | `PaymentMethod[]` |
| `createOrder(array $params)` | Create a payment order | `Order` |
| `getOrderByTradeId(string $tradeId)` | Query order by trade ID | `Order` |
| `getOrderByMchOrderId(string $mchOrderId)` | Query order by merchant order ID | `Order` |
| `getMerchantDetail()` | Get merchant info | `Merchant` |
| `activatePlugin(string $type)` | Activate plugin | `bool` |
| `webhook(?NonceStorageInterface $nonce)` | Create webhook handler (shares API Key) | `WebhookHandler` |

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
| `tradeId` | `string` | PonponPay trade ID |
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
$ponponpay = new PonponPay('your-api-key', [
    'api_url'        => 'https://api.ponponpay.com',  // API base URL
    'timeout'        => 30,                            // Request timeout (seconds)
    'debug'          => false,                         // Enable debug logging
    'debug_log_file' => '/tmp/ponponpay-debug.log',    // Debug log file path
]);
```

## Custom Nonce Storage

By default, the webhook handler uses file-based nonce storage. For high-traffic scenarios, implement `NonceStorageInterface` with Redis:

```php
use PonponPay\Nonce\NonceStorageInterface;

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
        return $this->redis->set('ponponpay_nonce:' . $nonce, '1', ['NX', 'EX' => $ttl]);
    }
}

// Usage
$handler = $ponponpay->webhook(new RedisNonceStorage($redis));
```

## Error Handling

```php
use PonponPay\Exception\ConfigException;
use PonponPay\Exception\ApiException;
use PonponPay\Exception\SignatureException;

try {
    $order = $ponponpay->createOrder([...]);
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
- [`payment_page.php`](./examples/payment_page.php) — Complete checkout page with UI

## License

MIT License. See [LICENSE](./LICENSE) for details.

---

# 安装

### 通过 Composer（推荐）

```bash
composer require ponponpay/php-sdk
```

### 手动安装

下载 SDK 并引入自动加载文件：

```php
require_once '/path/to/php-sdk/autoload.php';
```

## 快速开始

```php
use PonponPay\PonponPay;
use PonponPay\WebhookHandler;

// 初始化
$ponponpay = new PonponPay('你的API Key');

// 获取支付方式
$methods = $ponponpay->getPaymentMethods();

// 创建订单
$order = $ponponpay->createOrder([
    'mch_order_id' => 'ORDER_001',
    'currency'     => 'USDT',
    'network'      => 'tron',
    'amount'       => 10.00,
    'notify_url'   => 'https://your-site.com/webhook.php',
]);

// 跳转用户到支付页
header('Location: ' . $order->paymentUrl);

// 处理回调（自动共享 API Key）
$data = $ponponpay->webhook()->handle();
if (WebhookHandler::resolveStatus($data) === 'paid') {
    // 支付成功，更新订单状态
}
```

更多示例请参考 [`examples/`](./examples) 目录。
