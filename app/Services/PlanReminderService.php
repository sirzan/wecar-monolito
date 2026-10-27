<?php

namespace App\Services;

use App\Mail\PlanExpiring;
use App\Models\Agency;
use App\Models\PlanReminder;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class PlanReminderService
{
    private const THRESHOLDS = [7, 3, 1, 0];

    public function __construct(private readonly PushService $push) {}

    /**
     * @return array{subscriptions:int, agencies:int, reminders:int}
     */
    public function run(): array
    {
        $subscriptions = Subscription::query()
            ->with(['plan', 'user', 'agency.owner'])
            ->whereIn('status', ['active', 'expired'])
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now()->startOfDay(), now()->addDays(8)->endOfDay()])
            ->get();

        $sent = 0;
        $subscriptionsSent = 0;

        foreach ($subscriptions as $subscription) {
            $key = $this->reminderKey($subscription->ends_at);

            if (! $key || ($subscription->status === 'expired' && $this->hasActiveReplacement($subscription))) {
                continue;
            }

            $user = $subscription->kind === 'agency'
                ? $subscription->agency?->owner
                : $subscription->user;

            if (! $user) {
                continue;
            }

            if ($this->send($subscription, $key, $user, $this->contextForSubscription($subscription))) {
                $subscriptionsSent++;
                $sent++;
            }
        }

        $agencies = Agency::query()
            ->with('owner')
            ->whereNotNull('plan_expires_at')
            ->whereBetween('plan_expires_at', [now()->startOfDay(), now()->addDays(8)->endOfDay()])
            ->whereDoesntHave('subscriptions', fn ($query) => $query
                ->where('kind', 'agency')
                ->where('status', '!=', 'pending')
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->startOfDay())))
            ->get();

        $agenciesSent = 0;

        foreach ($agencies as $agency) {
            $key = $this->reminderKey($agency->plan_expires_at);

            if (! $key || ! $agency->owner) {
                continue;
            }

            if ($this->send($agency, $key, $agency->owner, $this->contextForAgency($agency))) {
                $agenciesSent++;
                $sent++;
            }
        }

        return [
            'subscriptions' => $subscriptionsSent,
            'agencies' => $agenciesSent,
            'reminders' => $sent,
        ];
    }

    private function hasActiveReplacement(Subscription $subscription): bool
    {
        return Subscription::query()
            ->where('kind', $subscription->kind)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->when($subscription->agency_id, fn ($query) => $query->where('agency_id', $subscription->agency_id))
            ->when(! $subscription->agency_id, fn ($query) => $query->where('user_id', $subscription->user_id))
            ->whereKeyNot($subscription->getKey())
            ->exists();
    }

    public function reminderKey(?Carbon $endsAt): ?string
    {
        if (! $endsAt) {
            return null;
        }

        $days = (int) floor(now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false));

        if ($days < 0) {
            return null;
        }

        return match (true) {
            $days <= 0 => '0d',
            $days <= 1 => '1d',
            $days <= 3 => '3d',
            $days <= 7 => '7d',
            default => null,
        };
    }

    private function send(Subscription|Agency $subject, string $key, User $user, array $context): bool
    {
        $reminder = PlanReminder::query()->firstOrCreate(
            [
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'reminder_key' => $key,
            ],
            ['sent_at' => now()],
        );

        if (! $reminder->wasRecentlyCreated) {
            return false;
        }

        if ($user->email) {
            Mail::to($user->email)->queue(new PlanExpiring(
                recipientName: $user->name,
                planName: $context['plan_name'],
                subjectName: $context['subject_name'],
                expiresAt: $context['expires_at'],
                renewalUrl: $context['url'],
                amount: $context['amount'],
                daysRemaining: (int) rtrim($key, 'd'),
            ));
        }

        UserNotification::query()->create([
            'user_id' => $user->getKey(),
            'title' => $context['title'],
            'message' => $context['message'],
            'link' => $context['url'],
            'type' => 'plan',
            'read' => false,
        ]);

        $payload = [
            'title' => $context['title'],
            'body' => $context['message'],
            'url' => $context['url'],
        ];

        foreach ($user->pushSubscriptions()->get() as $pushSubscription) {
            $this->push->send($pushSubscription, $payload);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function contextForSubscription(Subscription $subscription): array
    {
        $planName = $subscription->plan?->name ?? data_get($subscription->metadata, 'plan_name', 'Plan Wecar');
        $expiresAt = $subscription->ends_at;
        $isAgency = $subscription->kind === 'agency' && $subscription->agency;
        $subjectName = $isAgency ? $subscription->agency->name : $planName;
        $url = $isAgency
            ? route('panel.agency.billing', $subscription->agency)
            : ($subscription->kind === 'membership' ? route('panel.membership') : route('panel.billing'));

        return [
            'plan_name' => $planName,
            'subject_name' => $subjectName,
            'expires_at' => $expiresAt,
            'url' => $url,
            'amount' => (float) $subscription->amount,
            'title' => $isAgency ? 'Tu plan de agencia está por vencer' : 'Tu plan está por vencer',
            'message' => sprintf(
                '%s vence el %s. Renueva para no pausar tus publicaciones.',
                $subjectName,
                $expiresAt?->format('d/m/Y') ?? 'pronto',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contextForAgency(Agency $agency): array
    {
        $planName = $agency->planRelation?->name ?? ucfirst($agency->plan);

        return [
            'plan_name' => $planName,
            'subject_name' => $agency->name,
            'expires_at' => $agency->plan_expires_at,
            'url' => route('panel.agency.billing', $agency),
            'amount' => (float) ($agency->planRelation?->price ?? 0),
            'title' => 'La prueba de tu agencia está por vencer',
            'message' => sprintf(
                'La vigencia de %s termina el %s. Renueva para seguir publicando autos.',
                $agency->name,
                $agency->plan_expires_at?->format('d/m/Y') ?? 'pronto',
            ),
        ];
    }
}
