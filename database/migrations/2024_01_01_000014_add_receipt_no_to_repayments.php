<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayments', function (Blueprint $table) {
            $table->string('receipt_no')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('repayments', function (Blueprint $table) {
            $table->dropColumn('receipt_no');
        });
    }
};
