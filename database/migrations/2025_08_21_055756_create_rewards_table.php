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
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('reward_category_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('department_id')->index();
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->date('reward_date');
            $table->timestamps();

            $table->foreign('reward_category_id')->references('id')->on('reward_categories')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
