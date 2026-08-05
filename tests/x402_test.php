<?php
/**
 * Focused protocol tests for the x402 helper.
 */

require_once __DIR__ . '/../autoload.php';

use PolyPay\ApiClient;
use PolyPay\X402;

/** Test API client that records facilitator requests without network access. */
class X402TestApiClient extends ApiClient
{
    /** @var array<int,array<string,mixed>> Recorded requests */
    public array $requests = [];

    /** @var bool Whether the settlement represents a confirmed replay */
    public bool $replayed = false;

    /** Initialize the test client. */
    public function __construct()
    {
        parent::__construct('test-key');
    }

    /** Record and accept a verification request. */
    public function verifyX402(array $params): array
    {
        $this->requests[] = $params;
        return ['code' => 0, 'message' => '', 'data' => ['isValid' => true]];
    }

    /** Record and accept a settlement request. */
    public function settleX402(array $params): array
    {
        $this->requests[] = $params;
        return [
            'code' => 0,
            'message' => '',
            'data' => [
                'success' => true,
                'transaction' => '0xabc',
                'network' => 'eip155:8453',
                'paymentId' => 'X402-123',
                'replayed' => $this->replayed,
            ],
        ];
    }
}

$resource = [
    'resource' => 'https://merchant.example.com/api/premium-data',
    'method' => 'GET',
    'price' => '$0.01',
    'network' => 'eip155:8453',
    'payTo' => '0x1111111111111111111111111111111111111111',
    'description' => 'Premium market data',
    'mimeType' => 'application/json',
];

$v2 = new X402(new ApiClient('test-key'), ['resource' => $resource]);
$v2Response = $v2->requirementResponse();
$v2Required = json_decode(base64_decode($v2Response['headers']['PAYMENT-REQUIRED']), true);

assertSame(402, $v2Response['status'], 'v2 response status');
assertSame(2, $v2Required['x402Version'], 'v2 protocol version');
assertSame('10000', $v2Required['accepts'][0]['amount'], 'v2 atomic amount');
assertSame(
    '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
    $v2Required['accepts'][0]['asset'],
    'v2 USDC contract'
);
assertSame('eip3009', $v2Required['accepts'][0]['extra']['assetTransferMethod'], 'v2 transfer method');

$v1 = new X402(new ApiClient('test-key'), ['protocolVersion' => 1, 'resource' => $resource]);
$v1Response = $v1->requirementResponse();
$v1Required = json_decode($v1Response['body'], true);

assertSame(false, isset($v1Response['headers']['PAYMENT-REQUIRED']), 'v1 does not emit v2 header');
assertSame(1, $v1Required['x402Version'], 'v1 protocol version');
assertSame('10000', $v1Required['accepts'][0]['maxAmountRequired'], 'v1 atomic amount');

$legacyOnV2 = $v2->verifyAndSettle([
    'X-PAYMENT' => base64_encode(json_encode(['x402Version' => 2])),
]);
assertSame(false, $legacyOnV2['paid'], 'v2 ignores the legacy payment header');

$mockClient = new X402TestApiClient();
$proxyHelper = new X402($mockClient, ['resource' => $resource]);
$proxyRequired = json_decode(
    base64_decode($proxyHelper->requirementResponse()['headers']['PAYMENT-REQUIRED']),
    true
);
$paymentPayload = base64_encode(json_encode([
    'x402Version' => 2,
    'resource' => $proxyRequired['resource'],
    'accepted' => $proxyRequired['accepts'][0],
    'payload' => ['signature' => '0xsignature', 'authorization' => []],
]));
$proxyResult = $proxyHelper->verifyAndSettle(
    ['PAYMENT-SIGNATURE' => $paymentPayload],
    'GET'
);
assertSame(true, $proxyResult['paid'], 'proxy request settles successfully');
assertSame(true, $proxyResult['shouldFulfill'], 'first settlement may fulfill the resource');
assertSame('X402-123', $proxyResult['fulfillmentKey'], 'first settlement exposes a stable fulfillment key');
assertSame(
    $resource['resource'],
    $mockClient->requests[0]['resource'],
    'configured canonical URL is sent to facilitator'
);

$mockClient->replayed = true;
$replayResult = $proxyHelper->verifyAndSettle(
    ['PAYMENT-SIGNATURE' => $paymentPayload],
    'GET'
);
assertSame(true, $replayResult['paid'], 'confirmed replay remains paid');
assertSame(false, $replayResult['shouldFulfill'], 'confirmed replay cannot fulfill twice');
assertSame('X402-123', $replayResult['fulfillmentKey'], 'replay preserves the fulfillment key');

assertThrows(
    static fn() => new X402(new ApiClient('test-key'), [
        'resource' => array_merge($resource, [
            'network' => 'eip155:56',
            'assetContract' => $resource['payTo'],
        ]),
    ]),
    'unsupported x402 network',
    'unknown network is rejected before challenge creation'
);
assertThrows(
    static fn() => new X402(new ApiClient('test-key'), [
        'resource' => array_merge($resource, ['assetContract' => $resource['payTo']]),
    ]),
    'unsupported USDC contract',
    'wrong USDC contract is rejected before challenge creation'
);
assertThrows(
    static fn() => new X402(new ApiClient('test-key'), [
        'resource' => array_merge($resource, ['scheme' => 'upto']),
    ]),
    'unsupported x402 scheme',
    'unsupported scheme is rejected before challenge creation'
);
assertThrows(
    static fn() => new X402(new ApiClient('test-key'), [
        'resource' => array_merge($resource, [
            'extra' => ['assetTransferMethod' => 'permit2'],
        ]),
    ]),
    'unsupported x402 asset metadata',
    'unsupported transfer method is rejected before challenge creation'
);

echo "x402 protocol tests passed\n";

/**
 * Assert strict equality without requiring a test framework.
 *
 * @param mixed  $expected Expected value
 * @param mixed  $actual   Actual value
 * @param string $message  Assertion description
 * @return void
 */
function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

/**
 * Assert that a callback throws an exception containing the expected message.
 *
 * @param callable $callback        Callback under test
 * @param string   $expectedMessage Expected message fragment
 * @param string   $message         Assertion description
 * @return void
 */
function assertThrows(callable $callback, string $expectedMessage, string $message): void
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
