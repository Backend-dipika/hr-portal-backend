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
        Schema::create('resignation_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resignation_request_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('approver_role')->nullable(); 
            $table->string('approval_order')->nullable(); //level 1 or 2
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->date('approval_date')->nullable();       
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resignation_request_approvals');
    }
};
