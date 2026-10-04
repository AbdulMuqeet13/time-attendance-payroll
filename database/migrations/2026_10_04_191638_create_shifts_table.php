<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shift ending at or before its start time is overnight and belongs to the day it starts.
     * The nullable policy columns override the company attendance settings for this shift only.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('late_grace_minutes')->nullable();
            $table->unsignedSmallInteger('early_window_minutes')->nullable();
            $table->unsignedSmallInteger('checkout_grace_minutes')->nullable();
            $table->unsignedSmallInteger('half_day_minutes')->nullable();
            $table->unsignedSmallInteger('min_overtime_minutes')->nullable();
            $table->string('color', 20)->default('sky');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
