<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('interest_method', ['flat_rate', 'reducing_balance']);
            $table->decimal('interest_rate', 8, 4); // % per period
            $table->enum('repayment_frequency', ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly']);
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->integer('min_term')->default(1);
            $table->integer('max_term')->nullable();
            $table->enum('term_unit', ['days', 'weeks', 'months'])->default('months');
            $table->enum('processing_fee_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('processing_fee_value', 10, 4)->default(0);
            $table->enum('late_penalty_type', ['fixed', 'percentage_of_outstanding'])->default('fixed');
            $table->decimal('late_penalty_value', 10, 4)->default(0);
            $table->integer('grace_period_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
