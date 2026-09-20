<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'transactions',
            function (Blueprint $table) {

                $table
                    ->string(
                        'idempotency_key',
                        100
                    )
                    ->nullable();

                $table->unique(
                    [
                        'sender_wallet_id',
                        'idempotency_key',
                    ],
                    'transactions_sender_idempotency_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'transactions',
            function (Blueprint $table) {

                $table->dropUnique(
                    'transactions_sender_idempotency_unique'
                );

                $table->dropColumn(
                    'idempotency_key'
                );
            }
        );
    }
};