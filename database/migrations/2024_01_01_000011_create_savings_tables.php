<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->string('account_no')->unique();
            $table->string('product_name')->default('General Savings');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('interest_rate', 8, 4)->default(0); // annual %
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'borrower_id']);
            $table->index(['company_id', 'account_no']);
        });

        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'withdrawal', 'interest', 'fee']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('savings_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transactions');
        Schema::dropIfExists('savings_accounts');
    }
};
