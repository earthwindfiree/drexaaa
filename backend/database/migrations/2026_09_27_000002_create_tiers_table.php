<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('minimum_balance', 20, 2);
            $table->foreignId('strategy_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->json('benefits')->nullable();
            $table->json('feature_access')->nullable();
            $table->json('display_settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiers');
    }
};
