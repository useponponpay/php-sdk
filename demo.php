<?php
/**
 * PolyPay PHP SDK test script
 *
 * Run from the project root with: php demo.php
 * Tests all SDK features.
 */

require_once __DIR__ . '/autoload.php';

use PolyPay\PolyPay;
use PolyPay\WebhookHandler;
use PolyPay\Exception\PolyPayException;
use PolyPay\Exception\ApiException;
use PolyPay\Exception\ConfigException;
use PolyPay\Exception\SignatureException;
use PolyPay\Model\Order;
use PolyPay\Model\PaymentMethod;
use PolyPay\Nonce\FileNonceStorage;

// ========================================
// Configuration
// ========================================

// API Key and backend URL, replace with your actual values.
$apiKey = $argv[1] ?? 'YOUR_API_KEY_HERE';
$apiUrl = $argv[2] ?? 'https://api.polypay.ai';

echo "╔══════════════════════════════════════════╗\n";
echo "║   PolyPay PHP SDK — Integration Test   ║\n";
echo "╚══════════════════════════════════════════╝\n\n";
echo "API Key:  " . substr($apiKey, 0, 16) . "...\n";
echo "API URL:  {$apiUrl}\n";
echo str_repeat('─', 50) . "\n\n";

$passed = 0;
$failed = 0;

/**
 * Test helper function
 *
 * @param string   $name Test name
 * @param callable $fn   Test function, returns true on success
 * @return void
 */
function test(string $name, callable $fn): void
{
	global $passed, $failed;
	echo "▶ {$name}\n";
	try {
		$result = $fn();
		if ($result) {
			echo "  ✅ PASSED\n\n";
			$passed++;
		} else {
			echo "  ❌ FAILED\n\n";
			$failed++;
		}
	} catch (\Throwable $e) {
		echo "  ❌ EXCEPTION: " . get_class($e) . ": {$e->getMessage()}\n\n";
		$failed++;
	}
}

// ========================================
// 1. Basic functionality tests, no network required
// ========================================

echo "━━━ 1. 基础功能测试 ━━━\n\n";

test('ConfigException - 空 API Key', function () {
	try {
		new PolyPay('');
		return false;
	} catch (ConfigException $e) {
		echo "  → 捕获到: {$e->getMessage()}\n";
		return true;
	}
});

test('ConfigException - 空白 API Key', function () {
	try {
		new PolyPay('   ');
		return false;
	} catch (ConfigException $e) {
		echo "  → 捕获到: {$e->getMessage()}\n";
		return true;
	}
});

test('PolyPay 初始化', function () use ($apiKey) {
	$pp = new PolyPay($apiKey, ['api_url' => 'https://test.example.com']);
	echo "  → 实例创建成功\n";
	return $pp instanceof PolyPay;
});

test('PolyPay 带 debug 选项初始化', function () use ($apiKey) {
	$pp = new PolyPay($apiKey, [
		'api_url' => 'https://test.example.com',
		'timeout' => 10,
		'debug' => true,
		'debug_log_file' => '/tmp/polypay-test.log',
	]);
	echo "  → 带 debug 配置实例创建成功\n";
	return true;
});

test('Order 模型', function () {
	$order = Order::fromArray([
		'trade_id' => 'T20240101120000123456',
		'payment_url' => 'https://pay.polypay.ai/pay/T20240101120000123456',
		'amount' => 10.50,
		'actual_amount' => 10.50,
		'address' => 'TXxxxxxxxxxxxxxxxxxxxxxxxxx',
		'expires_at' => time() + 1800,
		'currency' => 'USDT',
		'network' => 'tron',
		'status' => 'pending',
		'tx_hash' => '',
		'mch_order_id' => 'ORDER_001',
	]);
	echo "  → Trade ID:     {$order->tradeId}\n";
	echo "  → Payment URL:  {$order->paymentUrl}\n";
	echo "  → Amount:       {$order->amount}\n";
	echo "  → Address:      {$order->address}\n";
	echo "  → Currency:     {$order->currency}\n";
	echo "  → Network:      {$order->network}\n";
	echo "  → Mch Order ID: {$order->mchOrderId}\n";

	return $order->tradeId === 'T20240101120000123456'
		&& $order->amount === 10.50
		&& $order->currency === 'USDT';
});

test('Order toArray()', function () {
	$data = ['trade_id' => 'T001', 'amount' => 5.0, 'network' => 'bsc'];
	$order = Order::fromArray($data);
	$arr = $order->toArray();
	echo "  → toArray keys: " . implode(', ', array_keys($arr)) . "\n";
	return $arr['trade_id'] === 'T001' && $arr['amount'] === 5.0;
});

test('PaymentMethod 模型', function () {
	$method = PaymentMethod::fromArray([
		'network' => 'Tron',
		'currencies' => ['USDT', 'USDC'],
	]);
	echo "  → Network:    {$method->network}\n";
	echo "  → Currencies: " . implode(', ', $method->currencies) . "\n";

	return $method->network === 'Tron'
		&& count($method->currencies) === 2
		&& $method->currencies[0] === 'USDT';
});

test('PaymentMethod toArray()', function () {
	$method = PaymentMethod::fromArray(['network' => 'Ethereum', 'currencies' => ['USDC']]);
	$arr = $method->toArray();
	return $arr['network'] === 'Ethereum' && $arr['currencies'] === ['USDC'];
});

test('ApiException 属性', function () {
	$e = new ApiException('Test error', 400, 1001, '{"code":1001}');
	echo "  → Message:  {$e->getMessage()}\n";
	echo "  → HTTP:     {$e->getHttpCode()}\n";
	echo "  → API Code: {$e->getApiCode()}\n";
	echo "  → Body:     {$e->getResponseBody()}\n";

	return $e->getHttpCode() === 400
		&& $e->getApiCode() === 1001
		&& $e->getResponseBody() === '{"code":1001}';
});

test('SignatureException 属性', function () {
	$e = new SignatureException('Invalid sig', 401);
	echo "  → Message:     {$e->getMessage()}\n";
	echo "  → HTTP Status: {$e->getHttpStatus()}\n";
	return $e->getHttpStatus() === 401;
});

// ========================================
// 2. Webhook signature verification tests, no network required
// ========================================

echo "━━━ 2. Webhook 签名验证测试 ━━━\n\n";

test('Webhook - webhook() 创建成功', function () use ($apiKey) {
	$pp = new PolyPay($apiKey);
	$handler = $pp->webhook();
	echo "  → WebhookHandler 实例创建成功\n";
	return $handler instanceof WebhookHandler;
});

test('Webhook - 签名验证通过', function () use ($apiKey) {
	$pp = new PolyPay($apiKey);
	$handler = $pp->webhook();

	// Simulate a valid callback.
	$body = json_encode(['order_no' => 'TEST_001', 'status' => 2, 'hash' => '0xabc123']);
	$timestamp = (string) time();
	$nonce = bin2hex(random_bytes(16));
	$keyHash = hash('sha256', $apiKey);
	$payload = $timestamp . "\n" . $nonce . "\n" . $body;
	$signature = hash_hmac('sha256', $payload, $keyHash);

	$headers = [
		'X-Key-Prefix' => substr($apiKey, 0, 12),
		'X-Timestamp' => $timestamp,
		'X-Nonce' => $nonce,
		'X-Signature' => $signature,
	];

	$data = $handler->verify($body, $headers);
	echo "  → Order No: {$data['order_no']}\n";
	echo "  → Status:   {$data['status']}\n";
	echo "  → TX Hash:  " . ($data['hash'] ?? $data['tx_hash'] ?? '') . "\n";

	return $data['order_no'] === 'TEST_001' && $data['status'] === 2;
});

test('Webhook - resolveStatus() 状态映射', function () {
	$cases = [
		['status' => 1, 'expected' => 'pending'],
		['status' => 2, 'expected' => 'paid'],
		['status' => 3, 'expected' => 'expired'],
		['status' => 4, 'expected' => 'cancelled'],
		['status' => 5, 'expected' => 'paid'],
		['status' => 6, 'expected' => 'pending'],
		['status' => 99, 'expected' => 'unknown'],
	];

	foreach ($cases as $case) {
		$result = WebhookHandler::resolveStatus(['status' => $case['status']]);
		echo "  → Status {$case['status']} → {$result}";
		if ($result !== $case['expected']) {
			echo " (expected: {$case['expected']}) ❌\n";
			return false;
		}
		echo " ✓\n";
	}
	return true;
});

test('Webhook - 无效签名拒绝', function () use ($apiKey) {
	$pp = new PolyPay($apiKey);
	$handler = $pp->webhook();

	$body = json_encode(['order_no' => 'TEST_002', 'status' => 2]);
	$timestamp = (string) time();
	$nonce = bin2hex(random_bytes(16));

	$headers = [
		'X-Key-Prefix' => substr($apiKey, 0, 12),
		'X-Timestamp' => $timestamp,
		'X-Nonce' => $nonce,
		'X-Signature' => 'invalid_signature_here',
	];

	try {
		$handler->verify($body, $headers);
		return false;
	} catch (SignatureException $e) {
		echo "  → 拒绝了无效签名: {$e->getMessage()} (HTTP {$e->getHttpStatus()})\n";
		return $e->getHttpStatus() === 401;
	}
});

test('Webhook - 过期时间戳拒绝', function () use ($apiKey) {
	$pp = new PolyPay($apiKey);
	$handler = $pp->webhook();

	$body = '{}';
	$timestamp = (string) (time() - 600); // 10 minutes ago

	$headers = [
		'X-Key-Prefix' => substr($apiKey, 0, 12),
		'X-Timestamp' => $timestamp,
		'X-Nonce' => bin2hex(random_bytes(16)),
		'X-Signature' => 'anything',
	];

	try {
		$handler->verify($body, $headers);
		return false;
	} catch (SignatureException $e) {
		echo "  → 拒绝了过期时间戳: {$e->getMessage()}\n";
		return true;
	}
});

test('Webhook - 错误的 Key Prefix 拒绝', function () use ($apiKey) {
	$pp = new PolyPay($apiKey);
	$handler = $pp->webhook();

	$headers = [
		'X-Key-Prefix' => 'wrong_prefix',
		'X-Timestamp' => (string) time(),
		'X-Nonce' => bin2hex(random_bytes(16)),
		'X-Signature' => 'anything',
	];

	try {
		$handler->verify('{}', $headers);
		return false;
	} catch (SignatureException $e) {
		echo "  → 拒绝了错误前缀: {$e->getMessage()}\n";
		return true;
	}
});

test('Webhook - 缺少签名头拒绝', function () use ($apiKey) {
	$pp = new PolyPay($apiKey);
	$handler = $pp->webhook();

	try {
		$handler->verify('{}', []);
		return false;
	} catch (SignatureException $e) {
		echo "  → 拒绝了缺少头: {$e->getMessage()}\n";
		return true;
	}
});

test('Nonce 防重放', function () use ($apiKey) {
	$pp = new PolyPay($apiKey);

	$body = json_encode(['order_no' => 'NONCE_TEST', 'status' => 2]);
	$timestamp = (string) time();
	$nonce = bin2hex(random_bytes(16));
	$keyHash = hash('sha256', $apiKey);
	$payload = $timestamp . "\n" . $nonce . "\n" . $body;
	$signature = hash_hmac('sha256', $payload, $keyHash);

	$headers = [
		'X-Key-Prefix' => substr($apiKey, 0, 12),
		'X-Timestamp' => $timestamp,
		'X-Nonce' => $nonce,
		'X-Signature' => $signature,
	];

	// The first verification should pass.
	$handler1 = $pp->webhook();
	$data = $handler1->verify($body, $headers);
	echo "  → 第一次验证: 通过 ✓\n";

	// Reusing the same nonce a second time should be rejected.
	try {
		$handler2 = $pp->webhook();
		$handler2->verify($body, $headers);
		echo "  → 第二次验证: 未拒绝 ✗\n";
		return false;
	} catch (SignatureException $e) {
		echo "  → 第二次验证: 拒绝 ({$e->getMessage()}) ✓\n";
		return true;
	}
});

// ========================================
// 3. FileNonceStorage tests
// ========================================

echo "━━━ 3. Nonce 存储测试 ━━━\n\n";

test('FileNonceStorage - 首次消费成功', function () {
	$storage = new FileNonceStorage(sys_get_temp_dir() . '/polypay_test_nonces_' . time());
	$result = $storage->consume('test_nonce_' . time(), 60);
	echo "  → consume() 返回: " . ($result ? 'true' : 'false') . "\n";
	return $result === true;
});

test('FileNonceStorage - 重复消费失败', function () {
	$storage = new FileNonceStorage(sys_get_temp_dir() . '/polypay_test_nonces_' . time());
	$nonce = 'test_nonce_dup_' . time();
	$first = $storage->consume($nonce, 60);
	$second = $storage->consume($nonce, 60);
	echo "  → 第一次: " . ($first ? 'true' : 'false') . "\n";
	echo "  → 第二次: " . ($second ? 'true' : 'false') . "\n";
	return $first === true && $second === false;
});

// ========================================
// 4. API integration tests, requires network access and a valid API Key
// ========================================

if ($apiKey !== 'YOUR_API_KEY_HERE') {
	echo "━━━ 4. API 联调测试 ━━━\n\n";

	$polypay = new PolyPay($apiKey, [
		'api_url' => $apiUrl,
		'debug' => true,
		'debug_log_file' => '/tmp/polypay-demo-debug.log',
	]);

	// Shared variables used by subsequent tests.
	$availableMethods = [];
	$createdTradeId = '';

	test('API - 获取支付方式', function () use ($polypay, &$availableMethods) {
		$methods = $polypay->getPaymentMethods();
		echo "  → 获取到 " . count($methods) . " 个网络\n";
		foreach ($methods as $m) {
			echo "    • {$m->network}: " . implode(', ', $m->currencies) . "\n";
		}
		$availableMethods = $methods;
		return count($methods) > 0;
	});

	test('API - 获取商户信息', function () use ($polypay) {
		$merchant = $polypay->getMerchantDetail();
		echo "  → 商户ID:   {$merchant->mchId}\n";
		echo "  → 商户名称: {$merchant->name}\n";
		return !empty($merchant->mchId);
	});

	test('API - 创建订单', function () use ($polypay, &$availableMethods, &$createdTradeId) {
		if (empty($availableMethods)) {
			echo "  → ⚠️ 无可用支付方式，跳过\n";
			return false;
		}

		// Use the first available network and currency.
		$method = $availableMethods[0];
		$network = $method->network;
		$currency = $method->currencies[0] ?? 'USDT';
		echo "  → 使用: {$network} / {$currency}\n";

		$order = $polypay->createOrder([
			'mch_order_id' => 'SDK_TEST_' . time(),
			'currency' => $currency,
			'network' => $network,
			'amount' => 1.00,
			'notify_url' => 'https://example.com/webhook',
			'redirect_url' => 'https://example.com/success',
		]);
		echo "  → Trade ID:    {$order->tradeId}\n";
		echo "  → Payment URL: {$order->paymentUrl}\n";
		echo "  → Amount:      {$order->amount}\n";
		echo "  → Address:     {$order->address}\n";
		$createdTradeId = $order->tradeId;
		return !empty($order->tradeId) && !empty($order->paymentUrl);
	});

	test('API - 查询订单（通过 Trade ID）', function () use ($polypay, &$createdTradeId) {
		if (empty($createdTradeId)) {
			echo "  → ⚠️ 无已创建的订单，跳过\n";
			return true;
		}
		try {
			$order = $polypay->getOrderByTradeId($createdTradeId);
			echo "  → Trade ID:   {$order->tradeId}\n";
			echo "  → Status:     {$order->status}\n";
			echo "  → Address:    {$order->address}\n";
			return $order->tradeId === $createdTradeId;
		} catch (ApiException $e) {
			echo "  → 查询失败: {$e->getMessage()}\n";
			return false;
		}
	});
} else {
	echo "━━━ 4. API 联调测试（跳过）━━━\n\n";
	echo "  ⚠️  未提供有效 API Key，跳过 API 联调测试\n";
	echo "  用法: php demo.php <API_KEY> [API_URL]\n";
	echo "  示例: php demo.php sk_test_xxxx https://api.polypay.ai\n\n";
}

// ========================================
// Test summary
// ========================================

echo str_repeat('═', 50) . "\n";
echo "测试完成: ✅ {$passed} passed, ❌ {$failed} failed\n";
echo str_repeat('═', 50) . "\n";

exit($failed > 0 ? 1 : 0);
