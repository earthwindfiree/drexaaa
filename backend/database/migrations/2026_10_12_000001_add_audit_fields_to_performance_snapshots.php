<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_snapshots', function (Blueprint $table) {
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('performance_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('note');
        });
    }
};
