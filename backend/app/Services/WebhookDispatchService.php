<?php

namespace App\Services;

use App\Services\SuperAdmin\PlatformSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatchService
{
    public function __construct(protected PlatformSettingsService $settingsService)
    {
    }

    /**
     * Dispatch a webhook event. Call this from any controller after an important action.
     *
     * @param string $event  e.g. 'subscription.created', 'gym.registered'
     * @param array  $data   Payload data
     */
    public function dispatch(string $event, array $data): void
    {
        try {
            $notifications = $this->settingsService->getAllSettings()['notifications'] ?? [];
            $url           = $notifications['webhook_url'] ?? '';
            $format        = $notifications['webhook_format'] ?? 'json';
            $secret        = $notifications['webhook_secret'] ?? '';
        } catch (\Throwable) {
            return;
        }

        if (empty($url)) {
            return;
        }

        $payload = [
            'event'     => $event,
            'timestamp' => now()->toIso8601String(),
            'data'      => $data,
        ];

        $signature = $secret ? hash_hmac('sha256', json_encode($payload), $secret) : null;

        $this->sendWithRetry($url, $payload, $format, $signature);
    }

    protected function sendWithRetry(string $url, array $payload, string $format, ?string $signature, int $maxRetries = 3): void
    {
        $headers = ['User-Agent' => 'GymSaaS-Webhook/1.0'];

        if ($signature) {
            $headers['X-Webhook-Signature'] = $signature;
        }

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $request = Http::withHeaders($headers)->timeout(10);

                $response = match ($format) {
                    'form'  => $request->asForm()->post($url, $this->flatten($payload)),
                    'slack' => $request->post($url, [
                        'text'   => "*[{$payload['event']}]* — " . now()->toDateTimeString(),
                        'blocks' => [
                            [
                                'type' => 'section',
                                'text' => [
                                    'type' => 'mrkdwn',
                                    'text' => "*Event:* `{$payload['event']}`\n*Time:* {$payload['timestamp']}\n```" . json_encode($payload['data'], JSON_PRETTY_PRINT) . "```",
                                ],
                            ],
                        ],
                    ]),
                    default => $request->post($url, $payload), // JSON
                };

                if ($response->successful()) {
                    return;
                }

                Log::warning("Webhook attempt {$attempt} failed with status {$response->status()} for URL: {$url}");
            } catch (\Throwable $e) {
                Log::warning("Webhook attempt {$attempt} threw exception: " . $e->getMessage());
            }

            if ($attempt < $maxRetries) {
                sleep(2 ** ($attempt - 1)); // 1s, 2s backoff
            }
        }

        Log::error("Webhook failed after {$maxRetries} attempts for URL: {$url}, event: {$payload['event']}");
    }

    /**
     * Flatten nested array for form-encoded payloads.
     */
    protected function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}[{$key}]" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }
}
