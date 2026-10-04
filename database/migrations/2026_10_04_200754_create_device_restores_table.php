<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Putting users and biometrics back onto a device from a backup (or from the app's current data),
     * tracked through the results of its command batch.
     */
    public function up(): void
    {
        Schema::create('device_restores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_backup_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('target_device_id')->constrained('devices')->cascadeOnDelete();
            $table->boolean('include_users')->default(true);
            $table->boolean('include_templates')->default(true);
            $table->boolean('clear_first')->default(false);
            $table->string('status', 20);
            $table->uuid('batch_id')->index();
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('templates_count')->default(0);
            $table->unsignedInteger('skipped_templates')->default(0);
            $table->unsignedInteger('total_commands')->default(0);
            $table->unsignedInteger('succeeded_commands')->default(0);
            $table->unsignedInteger('failed_commands')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_restores');
    }
};
