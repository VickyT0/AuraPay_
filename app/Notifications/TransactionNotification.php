<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Notifications\Notification;

class TransactionNotification extends Notification
{
    public function __construct(private Transaction $transaction) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Deliberately minimal payload — an id, a status, a risk level.
     * No amount, no counterparty details. This is the "minimal data
     * storage" principle from the diagram applied to notifications,
     * not just the audit log.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'transaction_id' => $this->transaction->id,
            'status' => $this->transaction->status,
            'risk_level' => $this->transaction->risk_level,
        ];
    }
}
