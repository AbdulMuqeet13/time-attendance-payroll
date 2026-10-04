<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ZKTeco devices talking ADMS push. A device registers itself on first contact with no branch
     * ("unclaimed"); its data is ignored until an admin claims it to a branch.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 64)->unique();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('model')->nullable();
            $table->string('firmware')->nullable();
            $table->string('push_version', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->string('last_attlog_stamp', 32)->nullable();
            $table->string('last_operlog_stamp', 32)->nullable();
            $table->unsignedInteger('user_count')->nullable();
            $table->unsignedInteger('fp_count')->nullable();
            $table->unsignedInteger('face_count')->nullable();
            $table->unsignedInteger('att_count')->nullable();
            $table->string('fp_algorithm', 20)->nullable();
            $table->string('face_algorithm', 20)->nullable();
            $table->string('auto_backup', 10)->default('none');
            $table->unsignedSmallInteger('backup_retention')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
