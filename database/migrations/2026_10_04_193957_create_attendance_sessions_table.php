<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check-in / check-out pairs within an attendance day. A break inside a shift makes a second session.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_day_id')->constrained()->cascadeOnDelete();
            $table->dateTime('check_in');
            $table->dateTime('check_out')->nullable();
            $table->foreignId('in_punch_id')->nullable()->constrained('attendance_punches')->nullOnDelete();
            $table->foreignId('out_punch_id')->nullable()->constrained('attendance_punches')->nullOnDelete();
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
