<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique(); // this is what gets encoded into the QR code
            $table->foreignId('receiver_wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->nullable(); // null = payer enters the amount
            $table->string('note')->nullable();
            $table->enum('status', ['pending', 'completed', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
