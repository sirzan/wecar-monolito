<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->string('reminder_key', 20);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'reminder_key']);
            $table->index(['subject_type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_reminders');
    }
};
