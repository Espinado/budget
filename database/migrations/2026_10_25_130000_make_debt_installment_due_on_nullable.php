<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_installments', function (Blueprint $table) {
            $table->date('due_on')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('debt_installments', function (Blueprint $table) {
            $table->date('due_on')->nullable(false)->change();
        });
    }
};
