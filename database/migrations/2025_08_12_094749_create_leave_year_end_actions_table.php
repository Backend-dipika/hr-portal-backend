<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_year_end_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->year('year');
            $table->enum('action_type', ['encash', 'carry_forward'])->nullable();
            $table->integer('days')->default(0);
            $table->date('processed_on')->nullable();
            $table->string('remarks')->nullable();

            // --- Fields Added for Approval Process ---
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected'])->default('submitted');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->timestamp('approval_date')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
            // Prevent duplicate processing for the same user/year/action
            $table->unique(['user_id', 'year', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_year_end_actions');
    }
};
