<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('managed_balance', 20, 2);
            $table->decimal('total_profit_loss', 20, 2);
            $table->decimal('performance_percentage', 9, 4);
            $table->timestamp('snapshot_at');
            $table->timestamps();
            $table->unique(['account_id', 'snapshot_at']);
            $table->index(['account_id', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_snapshots');
    }
};
