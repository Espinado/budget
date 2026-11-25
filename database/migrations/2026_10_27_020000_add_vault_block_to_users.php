<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('vault_failed_attempts')->default(0)->after('remember_token');
            $table->timestamp('vault_blocked_at')->nullable()->after('vault_failed_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vault_failed_attempts', 'vault_blocked_at']);
        });
    }
};
