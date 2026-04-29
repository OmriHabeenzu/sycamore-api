<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contribution schedules — what each member is expected to contribute
        Schema::create('contribution_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('borrower_id');
            $table->decimal('expected_amount', 12, 2);
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
        });

        // Actual contribution payments recorded
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('borrower_id');
            $table->unsignedBigInteger('contribution_schedule_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('contribution_date');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('contribution_schedules');
    }
};
