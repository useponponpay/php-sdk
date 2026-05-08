<?php
/**
 * x402 merchant integration helper
 *
 * This helper builds standard x402 payment requirements, returns HTTP 402
 * responses for unpaid requests, and delegates verification / settlement to
 * the PonponPay facilitator API.
 *
 * @package PonponPay
 */

namespace PonponPay;

use PonponPay\Exception\ApiException;
use PonponPay\Exception\ConfigException;

class X402
{
    /** @var string Header used by agents to submit a signed x402 payment */
    const PAYMENT_HEADER = 'x-payment';

    /** @var array<string,string> Supported Circle USDC contracts by x402 network */
    const USDC_CONTRACTS = [
        'eip155:8453' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        'eip155:1' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        'eip155:137' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
    ];

    /** @var ApiClient API client */
    private ApiClient $client;

    /** @var array Payment requirement */
    private array $requirement;

    /**
     * Constructor
     *
     * @param ApiClient $client  API client
     * @param array     $options x402 options:
     *   - resource (array) Payment requirement:
     *     - payTo             (string) Merchant settlement wallet address
     *     - resource          (string) Canonical protected resource URL
     *     - price             (string) Human-readable price, e.g. $0.01
     *     - maxAmountRequired (string) Token base-unit amount, optional if price is present
     *     - method            (string) HTTP method, optional
     *     - description       (string) Resource description, optional
     *     - mimeType          (string) Resource MIME type, optional
     *     - scheme            (string) Defaults to exact
     *     - network           (string) Defaults to eip155:8453
     *     - asset             (string) Defaults to USDC
     *     - assetContract     (string) Defaults to the network-specific Circle USDC contract
     *     - maxTimeoutSeconds (int) Defaults to 60
     * @throws ConfigException
     */
    public function __construct(ApiClient $client, array $options)
    {
        $resource = $options['resource'] ?? [];
        if (empty($resource['payTo'])) {
            throw new ConfigException('resource.payTo is required for x402');
        }
        if (empty($resource['resource'])) {
            throw new ConfigException('resource.resource is required for x402');
        }
        if (empty($resource['price']) && empty($resource['maxAmountRequired'])) {
            throw new ConfigException('resource.price or resource.maxAmountRequired is required for x402');
        }

        $network = $resource['network'] ?? 'eip155:8453';
        $this->client = $client;
        $this->requirement = array_merge([
            'scheme' => 'exact',
            'network' => $network,
            'asset' => 'USDC',
            'assetContract' => self::USDC_CONTRACTS[$network] ?? '',
            'maxTimeoutSeconds' => 60,
        ], $resource);
    }

    /**
     * Verify and settle the current request using its X-PAYMENT header
     *
     * @param array|null  $headers HTTP headers. Defaults to current request headers.
     * @param string|null $method  Current HTTP method. Defaults to $_SERVER['REQUEST_METHOD'].
     * @param string|null $url     Canonical current request URL. Defaults to the detected request URL.
     * @return array Result with paid, required, verify, and settle keys
     * @throws ApiException
     */
    public function verifyAndSettle(?array $headers = null, ?string $method = null, ?string $url = null): array
    {
        $headers = $this->normalizeHeaders($headers ?? $this->getRequestHeaders());
        $payment = trim((string)($headers[self::PAYMENT_HEADER] ?? ''));

        if ($payment === '') {
            return [
                'paid' => false,
                'required' => $this->requirementResponse(),
            ];
        }

        $current = [
            'method' => $method ?? ($_SERVER['REQUEST_METHOD'] ?? ($this->requirement['method'] ?? 'GET')),
            'resource' => $url ?? $this->currentRequestUrl(),
        ];

        $verify = $this->verify($payment, $current);
        if (empty($verify['isValid'])) {
            return [
                'paid' => false,
                'verify' => $verify,
                'required' => $this->requirementResponse(),
            ];
        }

        $settle = $this->settle($payment, $current);
        return [
            'paid' => !empty($settle['success']),
            'verify' => $verify,
            'settle' => $settle,
            'required' => $this->requirementResponse(),
        ];
    }

    /**
     * Verify an encoded X-PAYMENT payload
     *
     * @param string $payment Encoded X-PAYMENT payload
     * @param array  $current Current request metadata
     * @return array Verification result
     * @throws ApiException
     */
    public function verify(string $payment, array $current = []): array
    {
        $result = $this->client->verifyX402($this->buildFacilitatorPayload($payment, $current));
        $this->assertSuccess($result);
        return $result['data'] ?? [];
    }

    /**
     * Settle an encoded X-PAYMENT payload
     *
     * @param string $payment Encoded X-PAYMENT payload
     * @param array  $current Current request metadata
     * @return array Settlement result
     * @throws ApiException
     */
    public function settle(string $payment, array $current = []): array
    {
        $result = $this->client->settleX402($this->buildFacilitatorPayload($payment, $current));
        $this->assertSuccess($result);
        return $result['data'] ?? [];
    }

    /**
     * Build the HTTP 402 response metadata
     *
     * @return array Response metadata with status, headers, and body
     */
    public function requirementResponse(): array
    {
        return [
            'status' => 402,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'x402Version' => 1,
                'accepts' => [$this->requirement],
            ], JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Send a 402 requirement response and terminate the request
     *
     * @return void
     */
    public function sendRequirementAndExit(): void
    {
        $response = $this->requirementResponse();
        http_response_code($response['status']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $response['body'];
        exit;
    }

    /**
     * Get the configured payment requirement
     *
     * @return array
     */
    public function getRequirement(): array
    {
        return $this->requirement;
    }

    /**
     * Build the PonponPay facilitator request payload
     *
     * @param string $payment Encoded X-PAYMENT payload
     * @param array  $current Current request metadata
     * @return array
     */
    private function buildFacilitatorPayload(string $payment, array $current): array
    {
        return [
            'payment' => $payment,
            'paymentRequirements' => $this->requirement,
            'method' => $current['method'] ?? ($this->requirement['method'] ?? ''),
            'resource' => $current['resource'] ?? $this->requirement['resource'],
        ];
    }

    /**
     * Assert that the API response indicates success
     *
     * @param array $result API response data
     * @return void
     * @throws ApiException
     */
    private function assertSuccess(array $result): void
    {
        if (!isset($result['code']) || $result['code'] != 0) {
            throw new ApiException(
                $result['message'] ?? 'API request failed',
                200,
                (int)($result['code'] ?? -1),
                json_encode($result, JSON_UNESCAPED_UNICODE) ?: ''
            );
        }
    }

    /**
     * Get HTTP request headers
     *
     * @return array
     */
    private function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                return $headers;
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strncmp($key, 'HTTP_', 5) === 0) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        return $headers;
    }

    /**
     * Resolve the current request URL for x402 resource binding
     *
     * @return string
     */
    private function currentRequestUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($host === '' || $uri === '') {
            return $this->requirement['resource'];
        }

        $https = $_SERVER['HTTPS'] ?? '';
        $scheme = ($https !== '' && strtolower((string)$https) !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host . $uri;
    }

    /**
     * Normalize HTTP header names
     *
     * @param array $headers HTTP headers
     * @return array
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string)$key)] = is_array($value) ? implode(',', $value) : (string)$value;
        }
        return $normalized;
    }
}
