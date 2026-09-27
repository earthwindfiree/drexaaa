<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('network')->nullable();
            $table->string('wallet_address', 255);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['asset_id', 'wallet_address']);
            $table->index(['asset_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_wallets');
    }
};
