<?php

declare(strict_types=1);

namespace BitCore\Modules\Notifications\Services\Providers\Email;

use BitCore\Modules\Notifications\Services\Channels\EmailChannel;
use BitCore\Modules\Notifications\Services\Notification;
use BitCore\Application\Models\NotificationTemplate;
use BitCore\Modules\Notifications\Services\Providers\NotificationProviderAbstract;
use BitCore\Modules\Notifications\Services\Providers\HttpClientTrait;
use Psr\Log\LoggerInterface;

/**
 * Postmark Email Provider
 *
 * Sends notifications via Postmark API (https://postmarkapp.com)
 * Features:
 * - Highest deliverability rates in the industry
 * - Fast delivery (usually < 10 seconds)
 * - Excellent bounce handling
 * - Great customer support
 * - Detailed analytics and tracking
 * - Pricing: $15/month for 10K emails
 */
class PostmarkEmailProvider extends NotificationProviderAbstract
{
    use HttpClientTrait;

    private const API_BASE_URL = 'https://api.postmarkapp.com';

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return 'postmark';
    }

    /**
     * {@inheritDoc}
     */
    public function getChannelName(): string
    {
        return EmailChannel::$channelName;
    }

    /**
     * {@inheritDoc}
     */
    public function send(Notification $notification, NotificationTemplate $template, array $recipientsAddress): bool
    {
        $settings = $this->getSettings();
        $channelSettings = $this->getChannel()->getSettings() ?? [];

        try {
            $serverToken = $settings['server_token'] ?? '';
            if (empty($serverToken)) {
                logger()->error('Postmark: Server token is required');
                return false;
            }

            $fromEmail = $channelSettings['from_email'] ?? '';
            $fromName = $channelSettings['from_name'] ?? '';
            $replyTo = $channelSettings['reply_to'] ?? null;

            if (empty($fromEmail)) {
                logger()->error('Postmark: From email is required');
                return false;
            }

            // Postmark supports sending to multiple recipients, but we'll send individually for better tracking
            $results = [];
            foreach ($recipientsAddress as $email) {
                $payload = [
                    'From' => $fromName ? "$fromName <$fromEmail>" : $fromEmail,
                    'To' => $email,
                    'Subject' => $template->subject,
                    'HtmlBody' => $template->message,
                ];

                if ($replyTo) {
                    $payload['ReplyTo'] = $replyTo;
                }

                // Add tag if configured
                if (isset($settings['tag'])) {
                    $payload['Tag'] = $settings['tag'];
                }

                // Add metadata if configured
                if (isset($settings['metadata']) && is_array($settings['metadata'])) {
                    $payload['Metadata'] = $settings['metadata'];
                }

                // Add message stream if configured
                if (isset($settings['message_stream'])) {
                    $payload['MessageStream'] = $settings['message_stream'];
                }

                // Add tracking options
                if (isset($settings['track_opens'])) {
                    $payload['TrackOpens'] = (bool)$settings['track_opens'];
                }
                if (isset($settings['track_links'])) {
                    $payload['TrackLinks'] = $settings['track_links']; // None, HtmlAndText, HtmlOnly, TextOnly
                }

                $response = $this->makePostmarkApiRequest('POST', '/email', $payload, $serverToken);

                if (isset($response['MessageID'])) {
                    $results[] = $response['MessageID'];
                    logger()->info(
                        'Postmark: Email sent successfully',
                        ['email' => $email, 'message_id' => $response['MessageID']]
                    );
                } else {
                    logger()->error('Postmark: Failed to send email - no message ID returned', ['email' => $email]);
                    return false;
                }
            }

            return count($results) === count($recipientsAddress);
        } catch (\Exception $e) {
            logger()->error('Postmark: Email sending failed', ['error' => $e->getMessage(), 'exception' => $e]);
            return false;
        }
    }

    /**
     * Make API request to Postmark
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $serverToken Server token
     * @return array Response data
     * @throws \Exception
     */
    private function makePostmarkApiRequest(string $method, string $endpoint, array $data, string $serverToken): array
    {
        $url = $this->buildUrl(self::API_BASE_URL, $endpoint);

        $headers = array_merge(
            $this->getJsonHeaders(),
            ['X-Postmark-Server-Token: ' . $serverToken]
        );

        return $this->makeApiRequest($method, $url, $data, $headers);
    }

    /**
     * Override error handling for Postmark-specific error format
     */
    protected function extractErrorMessage(array $response): string
    {
        if (isset($response['Message'])) {
            $errorCode = $response['ErrorCode'] ?? 'UNKNOWN';
            return "[$errorCode] " . $response['Message'];
        }

        return $this->getDefaultErrorMessage($response);
    }

    /**
     * Get server information
     *
     * @return array|null Server information or null on error
     */
    public function getServerInfo(): ?array
    {
        try {
            $serverToken = $this->getSettings('server_token');
            if (empty($serverToken)) {
                return null;
            }

            return $this->makePostmarkApiRequest('GET', '/server', [], $serverToken);
        } catch (\Exception $e) {
            logger()->error('Postmark: Failed to get server info', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get delivery statistics
     *
     * @param string $tag Optional tag to filter by
     * @return array|null Statistics or null on error
     */
    public function getDeliveryStats(string|null $tag = null): ?array
    {
        try {
            $serverToken = $this->getSettings('server_token');
            if (empty($serverToken)) {
                return null;
            }

            $endpoint = '/deliverystats';
            if ($tag) {
                $endpoint .= '?tag=' . urlencode($tag);
            }

            return $this->makePostmarkApiRequest('GET', $endpoint, [], $serverToken);
        } catch (\Exception $e) {
            logger()->error('Postmark: Failed to get delivery stats', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get bounces
     *
     * @param int $count Number of bounces to retrieve (max 500)
     * @param int $offset Offset for pagination
     * @return array|null Bounces or null on error
     */
    public function getBounces(int $count = 100, int $offset = 0): ?array
    {
        try {
            $serverToken = $this->getSettings('server_token');
            if (empty($serverToken)) {
                return null;
            }

            $endpoint = "/bounces?count=$count&offset=$offset";
            return $this->makePostmarkApiRequest('GET', $endpoint, [], $serverToken);
        } catch (\Exception $e) {
            logger()->error('Postmark: Failed to get bounces', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Get message details
     *
     * @param string $messageId Message ID
     * @return array|null Message details or null on error
     */
    public function getMessageDetails(string $messageId): ?array
    {
        try {
            $serverToken = $this->getSettings('server_token');
            if (empty($serverToken)) {
                return null;
            }

            $endpoint = "/messages/outbound/$messageId/details";
            return $this->makePostmarkApiRequest('GET', $endpoint, [], $serverToken);
        } catch (\Exception $e) {
            logger()->error(
                'Postmark: Failed to get message details',
                ['error' => $e->getMessage(), 'exception' => $e]
            );
            return null;
        }
    }

    /**
     * Send batch emails
     *
     * @param array $emails Array of email data
     * @return array|null Batch results or null on error
     */
    public function sendBatch(array $emails): ?array
    {
        try {
            $serverToken = $this->getSettings('server_token');
            if (empty($serverToken)) {
                return null;
            }

            if (count($emails) > 500) {
                throw new \Exception('Postmark batch limit is 500 emails');
            }

            return $this->makePostmarkApiRequest('POST', '/email/batch', $emails, $serverToken);
        } catch (\Exception $e) {
            logger()->error('Postmark: Failed to send batch emails', ['error' => $e->getMessage(), 'exception' => $e]);
            return null;
        }
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw webhook payload
     * @param string $signature Webhook signature header
     * @param string $secret Webhook secret
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($signature, $expectedSignature);
    }
}
