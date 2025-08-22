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
        Schema::create('attendences', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->unsignedBigInteger('user_id');
            $table->string('day')->nullable();
            $table->date('date');
            $table->time('checkIn')->nullable();
            $table->time('checkOut')->nullable();
            $table->integer('working_minutes')->default(0);
            $table->enum('status', ['present', 'absent', 'halfday', 'partially_present'])->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendences');
    }
};
