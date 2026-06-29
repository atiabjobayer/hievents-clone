<?php

namespace HiEvents\Services\Infrastructure\Bkash;

use HiEvents\Exceptions\Bkash\BkashConfigurationException;
use HiEvents\Exceptions\Bkash\BkashPaymentException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

class BkashClientService
{
    private const CACHE_KEY_ID_TOKEN = 'bkash_id_token';
    private const CACHE_KEY_REFRESH_TOKEN = 'bkash_refresh_token';
    private const TOKEN_TTL_SECONDS = 3300; // Refresh 5 min before expiry (3600 - 300)

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly CacheRepository  $cache,
        private readonly LoggerInterface  $logger,
    )
    {
    }

    /**
     * Get a valid id_token, refreshing if necessary.
     * @throws BkashConfigurationException|BkashPaymentException
     */
    public function getIdToken(): string
    {
        $cachedToken = $this->cache->get(self::CACHE_KEY_ID_TOKEN);
        if ($cachedToken) {
            return $cachedToken;
        }

        $refreshToken = $this->cache->get(self::CACHE_KEY_REFRESH_TOKEN);
        if ($refreshToken) {
            return $this->refreshToken($refreshToken);
        }

        return $this->grantToken();
    }

    /**
     * Grant a new token from bKash.
     * @throws BkashConfigurationException|BkashPaymentException
     */
    public function grantToken(): string
    {
        $appKey = $this->config->get('services.bkash.app_key');
        $appSecret = $this->config->get('services.bkash.app_secret');
        $username = $this->config->get('services.bkash.username');
        $password = $this->config->get('services.bkash.password');
        $baseUrl = $this->config->get('services.bkash.base_url');

        if (empty($appKey) || empty($appSecret) || empty($username) || empty($password)) {
            throw new BkashConfigurationException(
                __('bKash API credentials are not configured.')
            );
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'username' => $username,
                'password' => $password,
            ])
                ->timeout(30)
                ->post($baseUrl . '/checkout/token/grant', [
                    'app_key' => $appKey,
                    'app_secret' => $appSecret,
                ]);

            if (!$response->successful()) {
                $this->logger->error('bKash grant token failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new BkashPaymentException(
                    __('Failed to authenticate with bKash. Please try again later.')
                );
            }

            $data = $response->json();
            $idToken = $data['id_token'] ?? null;
            $refreshToken = $data['refresh_token'] ?? null;

            if (!$idToken) {
                throw new BkashPaymentException(
                    __('Invalid token response from bKash.')
                );
            }

            $this->cache->put(self::CACHE_KEY_ID_TOKEN, $idToken, self::TOKEN_TTL_SECONDS);
            if ($refreshToken) {
                $this->cache->put(self::CACHE_KEY_REFRESH_TOKEN, $refreshToken, now()->addDays(27)->diffInSeconds());
            }

            $this->logger->info('bKash token granted successfully');

            return $idToken;
        } catch (ConnectionException $e) {
            $this->logger->error('bKash connection error during grant token', ['error' => $e->getMessage()]);
            throw new BkashPaymentException(
                __('Could not connect to bKash. Please try again later.')
            );
        }
    }

    /**
     * Refresh an existing token.
     * @throws BkashPaymentException
     */
    public function refreshToken(string $refreshTokenValue): string
    {
        $appKey = $this->config->get('services.bkash.app_key');
        $appSecret = $this->config->get('services.bkash.app_secret');
        $username = $this->config->get('services.bkash.username');
        $password = $this->config->get('services.bkash.password');
        $baseUrl = $this->config->get('services.bkash.base_url');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'username' => $username,
                'password' => $password,
            ])
                ->timeout(30)
                ->post($baseUrl . '/checkout/token/refresh', [
                    'app_key' => $appKey,
                    'app_secret' => $appSecret,
                    'refresh_token' => $refreshTokenValue,
                ]);

            if (!$response->successful()) {
                // Refresh failed, clear cache and try grant
                $this->logger->warning('bKash refresh token failed, falling back to grant token');
                $this->clearTokenCache();
                return $this->grantToken();
            }

            $data = $response->json();
            $idToken = $data['id_token'] ?? null;
            $newRefreshToken = $data['refresh_token'] ?? null;

            if (!$idToken) {
                $this->clearTokenCache();
                return $this->grantToken();
            }

            $this->cache->put(self::CACHE_KEY_ID_TOKEN, $idToken, self::TOKEN_TTL_SECONDS);
            if ($newRefreshToken) {
                $this->cache->put(self::CACHE_KEY_REFRESH_TOKEN, $newRefreshToken, now()->addDays(27)->diffInSeconds());
            }

            $this->logger->info('bKash token refreshed successfully');

            return $idToken;
        } catch (ConnectionException $e) {
            $this->logger->error('bKash connection error during refresh token', ['error' => $e->getMessage()]);
            throw new BkashPaymentException(
                __('Could not connect to bKash. Please try again later.')
            );
        }
    }

    /**
     * Create a payment on bKash.
     * @return array{paymentID: string, merchantInvoiceNumber: string, transactionStatus: string, hash: string}
     * @throws BkashPaymentException
     */
    public function createPayment(
        string $amount,
        string $currency,
        string $merchantInvoiceNumber,
        string $intent,
    ): array
    {
        $idToken = $this->getIdToken();
        $appKey = $this->config->get('services.bkash.app_key');
        $baseUrl = $this->config->get('services.bkash.base_url');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $idToken,
                'X-App-Key' => $appKey,
            ])
                ->timeout(30)
                ->post($baseUrl . '/checkout/payment/create', [
                    'amount' => $amount,
                    'currency' => $currency,
                    'merchantInvoiceNumber' => $merchantInvoiceNumber,
                    'intent' => $intent,
                ]);

            if (!$response->successful()) {
                $this->logger->error('bKash create payment failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'merchantInvoiceNumber' => $merchantInvoiceNumber,
                ]);
                throw new BkashPaymentException(
                    __('Failed to create bKash payment. Please try again.')
                );
            }

            $data = $response->json();

            if (empty($data['paymentID'])) {
                $this->logger->error('bKash create payment returned no paymentID', [
                    'response' => $data,
                ]);
                throw new BkashPaymentException(
                    __('Invalid response from bKash. Please try again.')
                );
            }

            $this->logger->info('bKash payment created', [
                'paymentID' => $data['paymentID'],
                'transactionStatus' => $data['transactionStatus'] ?? 'unknown',
            ]);

            return [
                'paymentID' => $data['paymentID'],
                'merchantInvoiceNumber' => $data['merchantInvoiceNumber'] ?? $merchantInvoiceNumber,
                'transactionStatus' => $data['transactionStatus'] ?? '',
                'hash' => $data['hash'] ?? '',
            ];
        } catch (ConnectionException $e) {
            $this->logger->error('bKash connection error during create payment', ['error' => $e->getMessage()]);
            throw new BkashPaymentException(
                __('Could not connect to bKash. Please try again later.')
            );
        }
    }

    /**
     * Execute (finalize) a payment on bKash.
     * @return array{paymentID: string, trxID: string, transactionStatus: string, amount: string, currency: string, customerMsisdn: string}
     * @throws BkashPaymentException
     */
    public function executePayment(string $paymentID): array
    {
        $idToken = $this->getIdToken();
        $appKey = $this->config->get('services.bkash.app_key');
        $baseUrl = $this->config->get('services.bkash.base_url');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $idToken,
                'X-App-Key' => $appKey,
            ])
                ->timeout(30)
                ->post($baseUrl . '/checkout/payment/execute/' . $paymentID);

            if (!$response->successful()) {
                $this->logger->error('bKash execute payment failed', [
                    'paymentID' => $paymentID,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new BkashPaymentException(
                    __('Failed to execute bKash payment. Please try again.')
                );
            }

            $data = $response->json();

            $this->logger->info('bKash execute payment raw response', [
                'paymentID' => $paymentID,
                'response' => $data,
            ]);

            // Check for explicit bKash error
            if (!empty($data['errorCode']) || !empty($data['errorMessage'])) {
                $this->logger->error('bKash execute payment returned error', [
                    'paymentID' => $paymentID,
                    'errorCode' => $data['errorCode'] ?? 'N/A',
                    'errorMessage' => $data['errorMessage'] ?? 'N/A',
                ]);

                // 9999 is a sandbox system error — suggest retry
                $userMessage = match ($data['errorCode'] ?? '') {
                    '2023' => __('Insufficient Balance'),
                    '9999' => __('bKash is temporarily unavailable. Please try again in a moment.'),
                    default => $data['errorMessage'] ?? __('Failed to execute bKash payment. Please try again.'),
                };

                throw new BkashPaymentException($userMessage);
            }

            return [
                'paymentID' => $data['paymentID'] ?? $paymentID,
                'trxID' => $data['trxID'] ?? null,
                'transactionStatus' => $data['transactionStatus'] ?? '',
                'amount' => $data['amount'] ?? '',
                'currency' => $data['currency'] ?? '',
                'customerMsisdn' => $data['customerMsisdn'] ?? '',
                'paymentExecuteTime' => $data['paymentExecuteTime'] ?? '',
                'payerReference' => $data['payerReference'] ?? '',
                'merchantInvoiceNumber' => $data['merchantInvoiceNumber'] ?? '',
            ];
        } catch (ConnectionException $e) {
            $this->logger->error('bKash connection error during execute payment', [
                'paymentID' => $paymentID,
                'error' => $e->getMessage(),
            ]);
            throw new BkashPaymentException(
                __('Could not reach bKash. Please check your connection and try again.')
            );
        }
    }

    /**
     * Query the status of a payment.
     * @return array{paymentID: string, trxID: string, transactionStatus: string, amount: string, currency: string}
     * @throws BkashPaymentException
     */
    public function queryPayment(string $paymentID): array
    {
        $idToken = $this->getIdToken();
        $appKey = $this->config->get('services.bkash.app_key');
        $baseUrl = $this->config->get('services.bkash.base_url');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $idToken,
                'X-App-Key' => $appKey,
            ])
                ->timeout(30)
                ->post($baseUrl . '/checkout/payment/query/status', [
                    'paymentID' => $paymentID,
                ]);

            if (!$response->successful()) {
                $this->logger->error('bKash query payment failed', [
                    'paymentID' => $paymentID,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new BkashPaymentException(
                    __('Failed to query bKash payment status.')
                );
            }

            $data = $response->json();

            return [
                'paymentID' => $data['paymentID'] ?? $paymentID,
                'trxID' => $data['trxID'] ?? null,
                'transactionStatus' => $data['transactionStatus'] ?? '',
                'amount' => $data['amount'] ?? '',
                'currency' => $data['currency'] ?? '',
                'customerMsisdn' => $data['customerMsisdn'] ?? '',
            ];
        } catch (ConnectionException $e) {
            $this->logger->error('bKash connection error during query payment', [
                'paymentID' => $paymentID,
                'error' => $e->getMessage(),
            ]);
            throw new BkashPaymentException(
                __('Could not connect to bKash. Please try again later.')
            );
        }
    }

    /**
     * Search for a transaction by trxID.
     * @return array
     * @throws BkashPaymentException
     */
    public function searchTransaction(string $trxID): array
    {
        $idToken = $this->getIdToken();
        $appKey = $this->config->get('services.bkash.app_key');
        $baseUrl = $this->config->get('services.bkash.base_url');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $idToken,
                'X-App-Key' => $appKey,
            ])
                ->timeout(30)
                ->post($baseUrl . '/checkout/general/searchTransaction', [
                    'trxID' => $trxID,
                ]);

            if (!$response->successful()) {
                $this->logger->error('bKash search transaction failed', [
                    'trxID' => $trxID,
                    'status' => $response->status(),
                ]);
                throw new BkashPaymentException(
                    __('Failed to search bKash transaction.')
                );
            }

            return $response->json();
        } catch (ConnectionException $e) {
            $this->logger->error('bKash connection error during search transaction', [
                'trxID' => $trxID,
                'error' => $e->getMessage(),
            ]);
            throw new BkashPaymentException(
                __('Could not connect to bKash. Please try again later.')
            );
        }
    }

    public function clearTokenCache(): void
    {
        $this->cache->forget(self::CACHE_KEY_ID_TOKEN);
        $this->cache->forget(self::CACHE_KEY_REFRESH_TOKEN);
    }
}
