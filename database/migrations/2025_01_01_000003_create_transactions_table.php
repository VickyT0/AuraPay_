<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('sender_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('receiver_wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('payment_request_id')->nullable()->constrained('payment_requests')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['a2a', 'qr', 'topup']);
            $table->enum('status', ['pending', 'completed', 'failed', 'flagged'])->default('pending');
            $table->unsignedTinyInteger('risk_score')->default(0); // 0-100
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sender_wallet_id', 'created_at']);
            $table->index(['receiver_wallet_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
