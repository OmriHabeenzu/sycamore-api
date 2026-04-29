<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('borrower_no');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('national_id')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('photo')->nullable();
            $table->enum('employment_status', ['employed', 'self_employed', 'unemployed'])->nullable();
            $table->string('employer')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'borrower_no']);
        });

        Schema::create('next_of_kin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone');
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_of_kin');
        Schema::dropIfExists('borrowers');
    }
};
