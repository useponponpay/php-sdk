<?php
/**
 * Main PonponPay SDK facade
 *
 * Merchants can use this class to perform all payment operations.
 *
 * Example:
 *   $ponponpay = new \PonponPay\PonponPay('your-api-key');
 *   $methods = $ponponpay->getPaymentMethods();
 *   $order = $ponponpay->createOrder([...]);
 *
 * @package PonponPay
 */

namespace PonponPay;

use PonponPay\Exception\ApiException;
use PonponPay\Model\Merchant;
use PonponPay\Model\Order;
use PonponPay\Model\PaymentMethod;
use PonponPay\Nonce\NonceStorageInterface;

class PonponPay
{
    /** @var string API Key */
    private string $apiKey;

    /** @var ApiClient API client */
    private ApiClient $client;

    /**
     * Constructor
     *
     * @param string $apiKey  API Key, required
     * @param array  $options Optional configuration:
     *   - api_url        (string) API base URL, defaults to https://api.ponponpay.com
     *   - timeout        (int)    Timeout in seconds, defaults to 30
     *   - debug          (bool)   Whether debug logging is enabled, defaults to false
     *   - debug_log_file (string) Debug log file path
     */
    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey;
        $this->client = new ApiClient(
            $apiKey,
            $options['api_url'] ?? '',
            $options['timeout'] ?? 30,
            $options['debug'] ?? false,
            $options['debug_log_file'] ?? null
        );
    }

    /**
     * Get payment methods available to the merchant
     *
     * @return PaymentMethod[] Payment method list
     * @throws ApiException
     */
    public function getPaymentMethods(): array
    {
        $result = $this->client->getPaymentMethods();
        $this->assertSuccess($result);

        $methods = [];
        foreach (($result['data']['methods'] ?? []) as $item) {
            $methods[] = PaymentMethod::fromArray($item);
        }

        return $methods;
    }

    /**
     * Create a payment order
     *
     * @param array $params Order parameters:
     *   - mch_order_id (string) Merchant order ID, required
     *   - currency     (string) Currency such as USDT or USDC, required
     *   - network      (string) Network such as tron, ethereum, bsc, solana, or polygon, required
     *   - amount       (float)  Amount, required
     *   - notify_url   (string) Webhook callback URL, required
     *   - redirect_url (string) Redirect URL after payment, optional
     * @return Order Order object
     * @throws ApiException
     */
    public function createOrder(array $params): Order
    {
        $result = $this->client->createOrder($params);
        $this->assertSuccess($result);

        return Order::fromArray($result['data'] ?? []);
    }

    /**
     * Query order details by trade ID
     *
     * @param string $tradeId Trade ID
     * @return Order Order object
     * @throws ApiException
     */
    public function getOrderByTradeId(string $tradeId): Order
    {
        $result = $this->client->getOrderDetail($tradeId);
        $this->assertSuccess($result);

        return Order::fromArray($result['data'] ?? []);
    }

    /**
     * Query order details by merchant order ID
     *
     * @param string $mchOrderId Merchant order ID
     * @return Order Order object
     * @throws ApiException
     */
    public function getOrderByMchOrderId(string $mchOrderId): Order
    {
        $result = $this->client->getOrderDetail('', $mchOrderId);
        $this->assertSuccess($result);

        return Order::fromArray($result['data'] ?? []);
    }

    /**
     * Get merchant details
     *
     * @return Merchant Merchant object
     * @throws ApiException
     */
    public function getMerchantDetail(): Merchant
    {
        $result = $this->client->getMerchantDetail();
        $this->assertSuccess($result);

        return Merchant::fromArray($result['data'] ?? []);
    }

    /**
     * Activate a plugin, typically called when the API Key is configured for the first time
     *
     * @param string $pluginType Plugin type identifier
     * @return bool Whether activation succeeded
     * @throws ApiException
     */
    public function activatePlugin(string $pluginType = 'php-sdk'): bool
    {
        $result = $this->client->activatePlugin($pluginType);
        return isset($result['code']) && $result['code'] == 0;
    }

    /**
     * Create a webhook handler that shares the same API Key
     *
     * @param NonceStorageInterface|null $nonceStorage Custom nonce storage, optional
     * @return WebhookHandler
     */
    public function webhook(?NonceStorageInterface $nonceStorage = null): WebhookHandler
    {
        return new WebhookHandler($this->apiKey, $nonceStorage);
    }

    /**
     * Create an x402 helper for agent payments
     *
     * @param array $options x402 configuration
     * @return X402
     */
    public function x402(array $options): X402
    {
        return new X402($this->client, $options);
    }

    /**
     * Get the underlying API client for advanced use cases
     *
     * @return ApiClient
     */
    public function getApiClient(): ApiClient
    {
        return $this->client;
    }

    /**
     * Assert that the API response indicates success
     *
     * @param array $result API response data
     * @throws ApiException If the business code is not 0
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
}
