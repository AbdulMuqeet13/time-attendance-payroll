<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backups of a device's users, biometric templates and attendance logs, stored as an encrypted,
     * gzipped JSON file. Device details are copied so a backup outlives the device (e.g. a broken unit).
     */
    public function up(): void
    {
        Schema::create('device_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_serial', 64);
            $table->string('device_name');
            $table->string('mode', 20);
            $table->boolean('include_users')->default(true);
            $table->boolean('include_templates')->default(true);
            $table->boolean('include_logs')->default(false);
            $table->date('logs_from')->nullable();
            $table->date('logs_to')->nullable();
            $table->string('status', 20);
            $table->uuid('batch_id')->nullable()->index();
            $table->dateTime('started_at');
            $table->dateTime('last_activity_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('counts')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('fp_algorithm', 20)->nullable();
            $table->string('face_algorithm', 20)->nullable();
            $table->string('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_backups');
    }
};
