<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('borrower_id');
            $table->enum('role', ['chairman', 'vice_chairman', 'treasurer', 'secretary', 'committee_member']);
            $table->date('appointed_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
        });

        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->date('meeting_date');
            $table->enum('meeting_type', ['general', 'agm', 'special', 'board'])->default('general');
            $table->string('agenda');
            $table->text('minutes')->nullable();
            $table->unsignedInteger('attendees_count')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes');
        Schema::dropIfExists('board_members');
    }
};
