<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Notifications\TransactionNotification;

class AuditService
{
    public function log(
        string $action,
        ?Transaction $transaction = null,
        ?int $userId = null,
        ?string $description = null,
        array $context = []
    ): AuditLog {
        $log = AuditLog::create([
            'transaction_id' => $transaction?->id,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'context' => $context,
        ]);

        // "Audit Trail and Notifications" — let the sender know the
        // outcome, using the minimal payload defined on the notification.
        if ($transaction && in_array($action, ['transaction.completed', 'transaction.flagged'], true)) {
            $transaction->senderWallet?->user?->notify(new TransactionNotification($transaction));
        }

        return $log;
    }
}
