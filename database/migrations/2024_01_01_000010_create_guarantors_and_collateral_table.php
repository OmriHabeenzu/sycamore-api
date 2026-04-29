<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->nullable()->constrained()->nullOnDelete(); // if guarantor is existing borrower
            $table->string('name');
            $table->string('phone');
            $table->string('national_id')->nullable();
            $table->string('relationship')->nullable();
            $table->string('address')->nullable();
            $table->string('employer')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('collateral', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['property', 'vehicle', 'equipment', 'inventory', 'other']);
            $table->string('description');
            $table->decimal('estimated_value', 15, 2);
            $table->string('serial_number')->nullable(); // vehicle reg, title deed no, etc.
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collateral');
        Schema::dropIfExists('guarantors');
    }
};
