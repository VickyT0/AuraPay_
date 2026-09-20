<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('scope', ['account_info', 'payment_initiation']);
            $table->enum('status', ['granted', 'revoked', 'expired'])->default('granted');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scope', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
