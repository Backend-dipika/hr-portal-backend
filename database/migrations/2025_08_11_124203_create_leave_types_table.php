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
        Schema::create('leave_types', function (Blueprint $table) {
           $table->id();
            // $table->enum('name', ['paid', 'unpaid', 'compoff', 'halfday', 'maternity']);
            $table->string('name')->unique();
            $table->string('type')->nullable();
            $table->text('code')->nullable();
            $table->integer('max_allowed_days')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
