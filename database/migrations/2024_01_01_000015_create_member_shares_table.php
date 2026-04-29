<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('borrower_id');
            $table->decimal('shares', 10, 2)->default(0);       // number of share units
            $table->decimal('amount_per_share', 10, 2);         // value of one share unit
            $table->decimal('total_paid', 12, 2)->default(0);   // total capital paid in
            $table->date('joined_date');
            $table->enum('status', ['active', 'suspended', 'withdrawn'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
            $table->unique(['company_id', 'borrower_id']); // one share account per member per company
        });

        Schema::create('share_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_share_id');
            $table->enum('type', ['purchase', 'dividend', 'withdrawal', 'adjustment']);
            $table->decimal('shares', 10, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('member_share_id')->references('id')->on('member_shares')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_transactions');
        Schema::dropIfExists('member_shares');
    }
};
