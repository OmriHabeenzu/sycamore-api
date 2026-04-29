<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('group_no');
            $table->string('name');
            $table->foreignId('loan_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('meeting_frequency', ['weekly', 'biweekly', 'monthly'])->nullable();
            $table->string('meeting_day')->nullable(); // e.g. "Monday"
            $table->string('meeting_location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'group_no']);
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['member', 'leader', 'secretary'])->default('member');
            $table->date('joined_at');
            $table->timestamps();

            $table->unique(['group_id', 'borrower_id']);
        });

        Schema::create('group_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cycle_no'); // e.g. "2024-001"
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['pending', 'approved', 'disbursed', 'active', 'closed'])->default('pending');
            $table->date('disbursement_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_loans');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
