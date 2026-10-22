<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\GoalLineNotification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends a notification to every device where the member turned on push. Devices the push service
 * reports as gone (uninstalled, permission revoked) are forgotten.
 */
class WebPushChannel
{
    public function __construct(private WebPush $webPush) {}

    public function send(User $notifiable, GoalLineNotification $notification): void
    {
        $subscriptions = $notifiable->pushSubscriptions;

        if ($subscriptions->isEmpty() || ! self::isConfigured()) {
            return;
        }

        $payload = json_encode($notification->toWebPush($notifiable), JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $this->webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $payload, ['TTL' => 60 * 60 * 12, 'urgency' => 'normal']);
        }

        foreach ($this->webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
            }
        }
    }

    /**
     * Whether VAPID keys are set. Without them, push quietly stays off.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }
}
