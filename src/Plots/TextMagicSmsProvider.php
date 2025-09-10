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
 * TextMagic SMS Provider
 *
 * Sends notifications via TextMagic SMS API (https://textmagic.com)
 * Features:
 * - Business-focused SMS platform
 * - 190+ countries coverage
 * - Good template and automation features
 * - Detailed analytics and reporting
 * - Pricing: ~$0.04 per SMS (varies by country)
 */
class TextMagicSmsProvider extends NotificationProviderAbstract
{
    use HttpClientTrait;

    private const API_BASE_URL = 'https://rest.textmagic.com/api/v2';

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return 'textmagic';
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
                logger()->error('TextMagic: Username and API key are required');
                return false;
            }

            // Clean and validate phone numbers
            $validNumbers = [];
            foreach ($recipientsAddress as $phoneNumber) {
                $cleanNumber = $this->cleanPhoneNumber($phoneNumber);
                if ($cleanNumber) {
                    $validNumbers[] = $cleanNumber;
                } else {
                    logger()->warning(
                        'TextMagic: Invalid phone number format',
                        ['number' => $phoneNumber]
                    );
                }
            }

            if (empty($validNumbers)) {
                logger()->error('TextMagic: No valid phone numbers to send to');
                return false;
            }

            $payload = [
                'text' => $template->message,
                'phones' => implode(',', $validNumbers),
            ];

            // Add from number if provided
            if (!empty($fromNumber)) {
                $payload['from'] = $fromNumber;
            }

            // Add optional parameters
            if (isset($settings['send_time'])) {
                $payload['sendingTime'] = $settings['send_time']; // Unix timestamp
            }

            if (isset($settings['contacts'])) {
                $payload['contacts'] = $settings['contacts']; // Contact IDs
            }

            if (isset($settings['lists'])) {
                $payload['lists'] = $settings['lists']; // List IDs
            }

            if (isset($settings['cut_extra'])) {
                $payload['cutExtra'] = (bool)$settings['cut_extra'] ? 1 : 0;
            }

            if (isset($settings['parts_count'])) {
                $payload['partsCount'] = (int)$settings['parts_count'];
            }

            if (isset($settings['reference_id'])) {
                $payload['referenceId'] = $settings['reference_id'];
            }

            $response = $this->makeTextMagicApiRequest(
                'POST',
                '/messages',
                $payload,
                $username,
                $apiKey
            );

            if (isset($response['id'])) {
                logger()->info('TextMagic: SMS sent successfully', [
                    'message_id' => $response['id'],
                    'recipients_count' => count($validNumbers),
                    'parts' => $response['parts'] ?? 1,
                    'price' => $response['price'] ?? 'unknown'
                ]);
                return true;
            }

            logger()->error('TextMagic: Failed to send SMS - no message ID returned');
            return false;
        } catch (\Exception $e) {
            logger()->error('TextMagic: SMS sending failed', ['error' => $e->getMessage(), 'exception' => $e]);
            return false;
        }
    }

    /**
     * Make API request to TextMagic
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $username Username
     * @param string $apiKey API key
     * @return array Response data
     * @throws \Exception
     */
    private function makeTextMagicApiRequest(
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
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^\d]/', '', $phoneNumber);

        // TextMagic expects numbers without + prefix
        // Basic validation: should be between 10-15 digits
        if (preg_match('/^\d{10,15}$/', $cleaned)) {
            return $cleaned;
        }

        return null;
    }

    /**
     * Override error handling for TextMagic-specific error format
     */
    protected function extractErrorMessage(array $response): string
    {
        if (isset($response['message'])) {
            $code = $response['code'] ?? 'UNKNOWN';
            return "[$code] " . $response['message'];
        }

        if (isset($response['errors']) && is_array($response['errors'])) {
            $errors = [];
            foreach ($response['errors'] as $field => $fieldErrors) {
                if (is_array($fieldErrors)) {
                    $errors[] = "$field: " . implode(', ', $fieldErrors);
                } else {
                    $errors[] = "$field: $fieldErrors";
                }
            }
            return implode('; ', $errors);
        }

        return $this->getDefaultErrorMessage($response);
    }

    /**
     * Get user information
     *
     * @return array|null User information or null on error
     */
    public function getUser(): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            return $this->makeTextMagicApiRequest('GET', '/user', [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('TextMagic: Failed to get user info', ['error' => $e->getMessage(), 'exception' => $e]);
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

            return $this->makeTextMagicApiRequest(
                'GET',
                '/user/balance',
                [],
                $username,
                $apiKey
            );
        } catch (\Exception $e) {
            logger()->error('TextMagic: Failed to get balance', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get pricing information
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

            $endpoint = '/messaging/price';
            if ($country) {
                $endpoint .= '?country=' . urlencode($country);
            }

            return $this->makeTextMagicApiRequest('GET', $endpoint, [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('TextMagic: Failed to get pricing', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get message history
     *
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array|null Message history or null on error
     */
    public function getHistory(int $page = 1, int $limit = 10): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            $endpoint = "/messages?page=$page&limit=$limit";

            return $this->makeTextMagicApiRequest('GET', $endpoint, [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('TextMagic: Failed to get history', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get message details by ID
     *
     * @param string $messageId Message ID
     * @return array|null Message details or null on error
     */
    public function getMessageDetails(string $messageId): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            return $this->makeTextMagicApiRequest(
                'GET',
                "/messages/$messageId",
                [],
                $username,
                $apiKey
            );
        } catch (\Exception $e) {
            logger()->error('TextMagic: Failed to get message details', [
                'error' => $e->getMessage(),
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Get available sender IDs
     *
     * @return array|null Sender IDs or null on error
     */
    public function getSenderIds(): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            return $this->makeTextMagicApiRequest('GET', '/senderids', [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('TextMagic: Failed to get sender IDs', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Create a template
     *
     * @param string $name Template name
     * @param string $text Template text
     * @return array|null Template information or null on error
     */
    public function createTemplate(string $name, string $text): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            $payload = [
                'name' => $name,
                'text' => $text,
            ];

            return $this->makeTextMagicApiRequest('POST', '/templates', $payload, $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('TextMagic: Failed to create template', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get templates
     *
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array|null Templates or null on error
     */
    public function getTemplates(int $page = 1, int $limit = 10): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            $endpoint = "/templates?page=$page&limit=$limit";

            return $this->makeTextMagicApiRequest('GET', $endpoint, [], $username, $apiKey);
        } catch (\Exception $e) {
            logger()->error('TextMagic: Failed to get templates', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Delete scheduled message
     *
     * @param string $messageId Message ID
     * @return bool Success status
     */
    public function deleteScheduledMessage(string $messageId): bool
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return false;
            }

            $this->makeTextMagicApiRequest(
                'DELETE',
                "/messages/$messageId",
                [],
                $username,
                $apiKey
            );
            return true;
        } catch (\Exception $e) {
            logger()->error(
                'TextMagic: Failed to delete scheduled message',
                ['error' => $e->getMessage(), 'exception' => $e]
            );
            return false;
        }
    }

    /**
     * Send message using template
     *
     * @param string $templateId Template ID
     * @param array $phones Phone numbers
     * @return array|null Response or null on error
     */
    public function sendWithTemplate(string $templateId, array $phones): ?array
    {
        try {
            $username = $this->getSettings('username');
            $apiKey = $this->getSettings('api_key');

            if (empty($username) || empty($apiKey)) {
                return null;
            }

            // Clean phone numbers
            $validNumbers = [];
            foreach ($phones as $phone) {
                $cleanNumber = $this->cleanPhoneNumber($phone);
                if ($cleanNumber) {
                    $validNumbers[] = $cleanNumber;
                }
            }

            if (empty($validNumbers)) {
                return null;
            }

            $payload = [
                'templateId' => $templateId,
                'phones' => implode(',', $validNumbers),
            ];

            return $this->makeTextMagicApiRequest(
                'POST',
                '/messages',
                $payload,
                $username,
                $apiKey
            );
        } catch (\Exception $e) {
            logger()->error(
                'TextMagic: Failed to send with template',
                ['error' => $e->getMessage(), 'exception' => $e]
            );
            return null;
        }
    }
}
