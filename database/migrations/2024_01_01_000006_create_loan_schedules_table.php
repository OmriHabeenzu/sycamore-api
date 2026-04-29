<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->integer('installment_no');
            $table->date('due_date');

            // Amounts due
            $table->decimal('principal_due', 15, 2)->default(0);
            $table->decimal('interest_due', 15, 2)->default(0);
            $table->decimal('fee_due', 15, 2)->default(0);
            $table->decimal('total_due', 15, 2)->default(0);

            // Amounts paid (updated as repayments come in)
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);

            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index(['loan_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schedules');
    }
};
