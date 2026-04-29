<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('loan_no');
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('group_loan_id')->nullable()->constrained('group_loans')->nullOnDelete();

            // Loan terms (copied from product at time of application)
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 8, 4);
            $table->enum('interest_method', ['flat_rate', 'reducing_balance']);
            $table->enum('repayment_frequency', ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly']);
            $table->integer('term');
            $table->enum('term_unit', ['days', 'weeks', 'months']);

            // Dates
            $table->date('application_date');
            $table->date('disbursement_date')->nullable();
            $table->date('first_repayment_date')->nullable();
            $table->date('maturity_date')->nullable();

            // Status
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'disbursed',
                'active', 'closed', 'written_off', 'defaulted'
            ])->default('pending');

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Disbursement
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->enum('disbursement_method', ['cash', 'mobile_money', 'bank'])->nullable();
            $table->string('disbursement_reference')->nullable();

            // Financials (calculated at disbursement, updated as payments come in)
            $table->decimal('total_interest', 15, 2)->default(0);
            $table->decimal('processing_fee', 15, 2)->default(0);
            $table->decimal('total_amount_due', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);

            // Arrears tracking
            $table->integer('days_in_arrears')->default(0);
            $table->boolean('is_overdue')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'loan_no']);
            $table->index(['company_id', 'status']);
            $table->index(['borrower_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
