<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_lines', function (Blueprint $table) {
            $table->foreignId('source_line_id')->nullable()->constrained('plan_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plan_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_line_id');
        });
    }
};
