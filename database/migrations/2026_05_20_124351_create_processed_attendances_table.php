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
        // Schema::create('processed_attendance', function (Blueprint $table) {
        //     $table->id();
        //     $table->timestamps();
        // });
        Schema::create('processed_attendances', function (Blueprint $table) {

            $table->id();

            $table->string('user_id', 50);

            $table->string('employee_name')
                ->nullable();

            $table->date('attendance_date');

            $table->time('checkin_time')
                ->nullable();

            $table->time('checkout_time')
                ->nullable();

            $table->timestamps();

            // UNIQUE CONSTRAINT
            $table->unique(
                ['user_id', 'attendance_date'],
                'unique_attendance'
            );
              $table->index(
                ['user_id', 'attendance_date'],
                'idx_processed_attendance_user_date'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_attendance');
    }
};
