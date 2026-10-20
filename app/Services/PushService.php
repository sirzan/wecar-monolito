<?php

namespace App\Services;

use App\Models\PushSubscription as PushSubscriptionModel;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushService
{
    public function configured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(PushSubscriptionModel $subscription, array $payload): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject', config('app.url')),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);

        $target = Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->p256dh,
            'authToken' => $subscription->auth,
        ]);

        $webPush->queueNotification($target, json_encode($payload));

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                return false;
            }
        }

        return true;
    }
}
