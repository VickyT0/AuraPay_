<?php

namespace App\Services;

use App\Models\Wallet;
use Carbon\Carbon;

/**
 * Simple, explainable rule-based scoring for a prototype.
 * Swap this out later for a real fraud model or third-party risk API —
 * the rest of the app only depends on score()'s return shape.
 */
class RiskScoringService
{
    private const HIGH_AMOUNT_THRESHOLD = 1000;
    private const VELOCITY_WINDOW_MINUTES = 60;
    private const VELOCITY_LIMIT = 5;

    public function score(Wallet $senderWallet, float $amount, string $type): array
    {
        $riskScore = 0;

        // 1. Large amount relative to a fixed threshold
        if ($amount >= self::HIGH_AMOUNT_THRESHOLD) {
            $riskScore += 30;
        }

        // 2. Large amount relative to the sender's own recent average
        $recentAverage = $senderWallet->sentTransactions()
            ->where('status', 'completed')
            ->latest()
            ->limit(10)
            ->avg('amount');

        if ($recentAverage && $amount > ($recentAverage * 3)) {
            $riskScore += 25;
        }

        // 3. Velocity: too many transactions in a short window
        $recentCount = $senderWallet->sentTransactions()
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::VELOCITY_WINDOW_MINUTES))
            ->count();

        if ($recentCount >= self::VELOCITY_LIMIT) {
            $riskScore += 25;
        }

        // 4. QR payments carry a bit more risk than a direct A2A transfer
        if ($type === 'qr') {
            $riskScore += 10;
        }

        // 5. Unusual hour (00:00-05:00) — a common naive fraud heuristic
        $hour = (int) Carbon::now()->format('H');
        if ($hour >= 0 && $hour < 5) {
            $riskScore += 10;
        }

        $riskScore = min($riskScore, 100);

        return [
            'score' => $riskScore,
            'level' => $this->levelFor($riskScore),
        ];
    }

    private function levelFor(int $score): string
    {
        return match (true) {
            $score >= 60 => 'high',
            $score >= 30 => 'medium',
            default => 'low',
        };
    }
}
