<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->string('kind')->default('bank')->after('direction');
            $table->foreignId('account_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('issued_transaction_id')->nullable()->after('account_id')->constrained('transactions')->nullOnDelete();
        });

        DB::table('debts')->where('direction', 'lend')->update(['kind' => 'personal']);
        DB::table('debts')->where('direction', 'borrow')->update(['kind' => 'bank']);
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_transaction_id');
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('kind');
        });
    }
};
