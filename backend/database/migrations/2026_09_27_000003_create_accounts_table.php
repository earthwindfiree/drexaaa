<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('managed_balance', 20, 2)->default(0);
            $table->decimal('pending_balance', 20, 2)->default(0);
            $table->decimal('total_profit_loss', 20, 2)->default(0);
            $table->decimal('performance_percentage', 9, 4)->default(0);
            $table->string('trading_status', 32)->default('inactive');
            $table->foreignId('tier_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
