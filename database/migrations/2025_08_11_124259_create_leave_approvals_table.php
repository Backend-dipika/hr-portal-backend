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
        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_request_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->integer('level')->default(0); // approval level (e.g., 1st, 2nd level)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('action_type', ['leave_approval', 'cancel_leave_approval', 'duration_modification']);
            $table->timestamps();

            //Foreign keys
            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_approvals');
    }
};
