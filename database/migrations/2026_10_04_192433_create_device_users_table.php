<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The users each device last reported (USERINFO / OPERLOG), used to reconcile devices with employees.
     */
    public function up(): void
    {
        Schema::create('device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('pin', 20);
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('privilege')->default(0);
            $table->string('card', 50)->nullable();
            $table->boolean('has_password')->default(false);
            $table->unsignedTinyInteger('fingerprint_count')->default(0);
            $table->boolean('has_face')->default(false);
            $table->dateTime('seen_at');
            $table->timestamps();

            $table->unique(['device_id', 'pin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_users');
    }
};
