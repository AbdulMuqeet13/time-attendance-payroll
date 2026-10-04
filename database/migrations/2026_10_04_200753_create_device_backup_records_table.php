<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data a device uploads while its backup is collecting; folded into the backup file and deleted
     * when the backup finishes.
     */
    public function up(): void
    {
        Schema::create('device_backup_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_backup_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->longText('payload');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_backup_records');
    }
};
