<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->enum('charge_type', ['processing_fee', 'insurance', 'other']);
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reason')->nullable();
            $table->date('applied_at');
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'is_paid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
        Schema::dropIfExists('loan_charges');
    }
};
