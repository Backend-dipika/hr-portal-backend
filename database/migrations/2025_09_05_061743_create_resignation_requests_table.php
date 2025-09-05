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
        Schema::create('resignation_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('requested_by_id');
            $table->enum('status', ['resignation', 'termination'])->default('resignation');
            $table->date('submission_date');
            $table->date('effective_date')->nullable();
            $table->date('notice_period_end_date ')->nullable();
            $table->text('reason')->nullable();
            $table->enum('final_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('document')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('requested_by_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resignation_requests');
    }
};
