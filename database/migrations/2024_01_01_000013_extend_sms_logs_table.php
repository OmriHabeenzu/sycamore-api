<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('borrower_id')->constrained()->nullOnDelete();
            $table->text('response')->nullable()->after('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            $table->dropColumn(['loan_id', 'response']);
        });
    }
};
