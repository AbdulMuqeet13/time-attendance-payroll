<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fingerprint, face and palm templates read from devices. Templates are personal data: the
     * template column is encrypted by the model. One row per employee, type and finger/slot index.
     */
    public function up(): void
    {
        Schema::create('biometric_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedTinyInteger('finger_index')->default(0);
            $table->longText('template');
            $table->unsignedInteger('size')->nullable();
            $table->string('valid_flag', 5)->default('1');
            $table->string('storage', 20);
            $table->unsignedSmallInteger('biodata_type')->nullable();
            $table->string('major_version', 10)->nullable();
            $table->string('minor_version', 10)->nullable();
            $table->string('format', 10)->nullable();
            $table->foreignId('source_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->dateTime('captured_at');
            $table->timestamps();

            $table->unique(['employee_id', 'type', 'finger_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_templates');
    }
};
