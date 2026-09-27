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
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['deposit', 'withdrawal', 'adjustment']);
            $table->enum('status', ['completed', 'pending', 'rejected']);
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deposit_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->decimal('crypto_amount', 36, 18)->nullable();
            $table->decimal('usd_amount', 20, 2);
            $table->decimal('price_snapshot', 20, 8)->nullable();
            $table->string('reference', 255);
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
            $table->index(['account_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
