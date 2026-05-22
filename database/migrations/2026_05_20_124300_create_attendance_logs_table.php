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
        // Schema::create('attendance_logs', function (Blueprint $table) {
        //     $table->id();
        //     $table->timestamps();
        // });
          Schema::create('attendance_logs', function (Blueprint $table) {

            $table->id();

            $table->string('device_sn')->nullable();

            $table->string('user_id', 50);

            $table->timestamp('punch_time');

            $table->string('status', 10)->nullable();

            $table->string('verify', 10)->nullable();

            $table->text('raw_data')->nullable();

            $table->timestamps();

            // Composite Index
            $table->index(
                ['user_id', 'punch_time'],
                'idx_attendance_logs_user_time'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
