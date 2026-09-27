<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_wallet_id')->constrained()->restrictOnDelete();
            $table->decimal('crypto_amount', 36, 18);
            $table->decimal('price_snapshot', 20, 8);
            $table->decimal('usd_value', 20, 2);
            $table->string('transaction_reference', 255);
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['account_id', 'status']);
            $table->index('transaction_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
