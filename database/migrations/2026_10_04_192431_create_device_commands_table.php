<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commands queued for a device. The device fetches them with GET /iclock/getrequest and reports
     * the result through POST /iclock/devicecmd. A failed command does not block the ones after it.
     */
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch_id')->nullable()->index();
            $table->unsignedInteger('sequence');
            $table->string('type', 40);
            $table->text('command');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->integer('return_code')->nullable();
            $table->text('response')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['device_id', 'sequence']);
            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
