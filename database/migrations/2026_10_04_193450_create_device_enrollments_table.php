<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which devices hold each employee, and how the last push or removal went.
     */
    public function up(): void
    {
        Schema::create('device_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->uuid('batch_id')->nullable()->index();
            $table->string('message')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_enrollments');
    }
};
