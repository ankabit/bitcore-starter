<?php

declare(strict_types=1);

namespace BitCore\Modules\Notifications\Services\Providers\SMS;

use BitCore\Modules\Notifications\Services\Channels\SmsChannel;
use BitCore\Modules\Notifications\Services\Notification;
use BitCore\Application\Models\NotificationTemplate;
use BitCore\Modules\Notifications\Services\Providers\NotificationProviderAbstract;
use BitCore\Modules\Notifications\Services\Providers\HttpClientTrait;
use Psr\Log\LoggerInterface;

/**
 * ClickSend SMS Provider
 *
 * Sends notifications via ClickSend SMS API (https://clicksend.com)
 * Features:
 * - Feature-rich platform with web interface
 * - 220+ countries coverage
 * - Multiple communication channels
 * - Good business features
 * - Pricing: ~$0.026 per SMS (varies by country)
 */
class ClickSendSmsProvider extends NotificationProviderAbstract
{
    use HttpClientTrait;

    private const API_BASE_URL = 'https://rest.clicksend.com/v3';

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return 'clicksend';
    }

    /**
     * {@inheritDoc}
     */
    public function getChannelName(): string
    {
        return SmsChannel::$channelName;
    }

    /**
     * {@inheritDoc}
     */
    public function send(Notification $notification, NotificationTemplate $template, array $recipientsAddress): bool
    {
        $settings = $this->getSettings();
        $channelSettings = $this->getChannel()->getSettings() ?? [];

        try {
            $username = $settings['username'] ?? '';
            $apiKey = $settings['api_key'] ?? '';
            $fromNumber = $settings['from_number'] ?? $channelSettings['from_number'] ?? '';

            if (empty($username) || empty($apiKey)) {
                logger()->error('ClickSend: Username and API key are required');
                return false;
            }

            if (empty($fromNumber)) {
                logger()->error('ClickSend: From number is required');
                return false;
            }

            // Prepare messages array
            $messages = [];
            foreach ($recipientsAddress as $phoneNumber) {
                // Clean and validate phone number
                $cleanNumber = $this->cleanPhoneNumber($phoneNumber);
                if (!$cleanNumber) {
                    logger()->warning(
                        'ClickSend: Invalid phone number format',
                        ['number' => $phoneNumber]
                    );
                    continue;
                }

                $message = [
                    'to' => $cleanNumber,
                    'body' => $template->message,
                    'from' => $fromNumber,
                ];

                // Add optional parameters
                if (isset($settings['source'])) {
                    $message['source'] = $settings['source']; // 'sdk', 'api', 'optimized'
                }

                if (isset($settings['schedule'])) {
                    $message['schedule'] = $settings['schedule']; // Unix timestamp
                }

                if (isset($settings['custom_string'])) {
                    $message['custom_string'] = $settings['custom_string'];
                }

                if (isset($settings['list_id'])) {
                    $message['list_id'] = $settings['list_id'];
                }

                $messages[] = $message;
            }

            if (empty($messages)) {
                logger()->error('ClickSend: No valid phone numbers to send to');
                return false;
            }

            $payload = [
                'messages' => $messages,
            ];

            $response = $this->makeClickSendApiRequest('POST', '/sms/send', $payload, $username, $apiKey);

            $successCount = 0;
            if (isset($response['data']['messages'])) {
                foreach ($response['data']['messages'] as $messageResult) {
                    if (isset($messageResult['status']) && $messageResult['status'] === 'SUCCESS') {
                        $successCount++;
                        logger()->info('ClickSend: SMS sent successfully', [
                            'message_id' => $messageResult['message_id'] ?? 'unknown',
                            'to' => $messageResult['to'] ?? 'unknown',
                            'message_price' => $messageResult['message_price'] ?? 'unknown'
                        ]);
                    } else {
                        logger()->error('ClickSend: Failed to send SMS', [
                            'to' => $messageResult['to'] ?? 'unknown',
                            'status' => $messageResult['status'] ?? 'unknown',
                            'error' => $messageResult['error_text'] ?? 'Unknown error'
                        ]);
                    }
                }
            }

            return $successCount > 0;
        } catch (\Exception $e) {
            logger()->error('ClickSend: SMS sending failed', ['error' => $e->getMessage(), 'exception' => $e]);
            return false;
        }
    }

    /**
     * Make API request to ClickSend
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $username Username
     * @param string $apiKey API key
     * @return array Response data
     * @throws \Exception
     */
    private function makeClickSendApiRequest(
        string $method,
        string $endpoint,
        array $data,
        string $username,
        string $apiKey
    ): array {
        $url = $this->buildUrl(self::API_BASE_URL, $endpoint);

        $headers = array_merge(
            $this->getJsonHeaders(),
            [$this->buildAuthHeader('basic', $username . ':' . $apiKey)]
        );

        return $this->makeApiRequest($method, $url, $data, $headers);
    }

    /**
     * Clean and validate phone number format
     *
     * @param string $phoneNumber
     * @return string|null Cleaned phone number or null if invalid
     */
    private function cleanPhoneNumber(string $phoneNumber): ?string
    {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);

        // Ensure it starts with + for international format
        if (!str_starts_with($cleaned, '+')) {
            // Add country code if missing (this is basic - should be configurable)
            $defaultCountryCode = $this->getSettings('default_country_code') ?? '+1';
            $cleaned = $defaultCountryCode . $cleaned;
        }

        // Basic validation: should be between 10-15 digits after the +
        if (preg_match('/^\+\d{10,15}$/', $cleaned)) {
            return $cleaned;
        }

        return null;
    }

    /**
     * Override error handling for ClickSend-specific error format
     */
    protected function extractErrorMessage(array $response): string
    {
        if (isset($response['response_msg'])) {
            $responseCode = $response['response_code'] ?? 'UNKNOWN';
            return "[$responseCode] " . $response['response_msg'];
        }

        return $this->getDefaultErrorMessage($response);
    }

    /**
     * Get account information
     *
     * @return array|null Account information or null on error
     */
    public function getAccount(): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            return $this->makeClickSendApiRequest('GET', '/account', [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('ClickSend: Failed to get account info', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get account balance
     *
     * @return array|null Balance information or null on error
     */
    public function getBalance(): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            return $this->makeClickSendApiRequest('GET', '/account/balance', [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('ClickSend: Failed to get balance', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get SMS pricing
     *
     * @param string|null $country Country code (optional)
     * @return array|null Pricing information or null on error
     */
    public function getPricing(string|null $country = null): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            $endpoint = '/sms/price';
            if ($country) {
                $endpoint .= '?country=' . urlencode($country);
            }

            return $this->makeClickSendApiRequest('GET', $endpoint, [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('ClickSend: Failed to get pricing', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get SMS history
     *
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array|null SMS history or null on error
     */
    public function getHistory(int $page = 1, int $limit = 15): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            $endpoint = "/sms/history?page=$page&limit=$limit";

            return $this->makeClickSendApiRequest('GET', $endpoint, [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('ClickSend: Failed to get history', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get SMS delivery receipts
     *
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array|null Delivery receipts or null on error
     */
    public function getDeliveryReceipts(int $page = 1, int $limit = 15): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            $endpoint = "/sms/receipts?page=$page&limit=$limit";

            return $this->makeClickSendApiRequest('GET', $endpoint, [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error(
                'ClickSend: Failed to get delivery receipts',
                [
                'error' => $e->getMessage(),
                'exception' => $e
                ]
            );
            return null;
        }
    }

    /**
     * Create SMS template
     *
     * @param string $name Template name
     * @param string $body Template body
     * @return array|null Template information or null on error
     */
    public function createTemplate(string $name, string $body): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            $payload = [
                'template_name' => $name,
                'template_body' => $body,
            ];

            return $this->makeClickSendApiRequest('POST', '/sms/templates', $payload, $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('ClickSend: Failed to create template', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get SMS templates
     *
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array|null Templates or null on error
     */
    public function getTemplates(int $page = 1, int $limit = 15): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            $endpoint = "/sms/templates?page=$page&limit=$limit";

            return $this->makeClickSendApiRequest('GET', $endpoint, [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('ClickSend: Failed to get templates', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Cancel scheduled SMS
     *
     * @param string $messageId Message ID
     * @return bool Success status
     */
    public function cancelScheduledSms(string $messageId): bool
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return false;
            }

            $response = $this->makeClickSendApiRequest(
                'PUT',
                "/sms/$messageId/cancel",
                [],
                $username,
                $apiKey
            );
            return isset($response['data']);
        } catch (\Exception $e) {
            logger()->error(
                'ClickSend: Failed to cancel scheduled SMS',
                ['error' => $e->getMessage(), 'exception' => $e]
            );
            return false;
        }
    }
}
