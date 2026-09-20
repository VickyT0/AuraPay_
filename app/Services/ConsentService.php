<?php

namespace App\Services;

use App\Models\Consent;
use App\Models\User;

class ConsentService
{
    /**
     * Grant a user's consent for a scope (e.g. "account_info" or
     * "payment_initiation"). Defaults to a 30-day expiry — consent
     * that never expires isn't really consent.
     */
    public function grant(User $user, string $scope, ?int $ttlMinutes = 43200): Consent
    {
        // A user only ever has one live consent per scope.
        $user->consents()
            ->where('scope', $scope)
            ->where('status', 'granted')
            ->update(['status' => 'revoked']);

        return Consent::create([
            'user_id' => $user->id,
            'scope' => $scope,
            'status' => 'granted',
            'granted_at' => now(),
            'expires_at' => $ttlMinutes ? now()->addMinutes($ttlMinutes) : null,
        ]);
    }

    public function revoke(Consent $consent): void
    {
        $consent->update(['status' => 'revoked']);
    }

    public function hasActive(User $user, string $scope): bool
    {
        return $user->consents()
            ->where('scope', $scope)
            ->where('status', 'granted')
            ->get()
            ->contains(fn (Consent $consent) => $consent->isActive());
    }
}
