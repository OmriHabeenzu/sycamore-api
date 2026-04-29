<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->year('year');
            $table->decimal('total_surplus', 14, 2);         // net income for the year
            $table->decimal('distributable_amount', 14, 2);  // amount approved for distribution
            $table->decimal('per_share_rate', 10, 4);        // dividend per share unit
            $table->enum('status', ['draft', 'approved', 'distributed'])->default('draft');
            $table->date('approved_at')->nullable();
            $table->date('distributed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['company_id', 'year']);
        });

        Schema::create('dividend_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dividend_id');
            $table->unsignedBigInteger('borrower_id');
            $table->decimal('shares', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->boolean('is_paid')->default(false);
            $table->date('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->timestamps();

            $table->foreign('dividend_id')->references('id')->on('dividends')->onDelete('cascade');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividend_allocations');
        Schema::dropIfExists('dividends');
    }
};
