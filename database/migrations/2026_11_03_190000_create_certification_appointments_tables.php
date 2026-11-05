<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('folio')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('whatsapp', 30);
            $table->string('state', 80);

            $table->string('brand', 80);
            $table->string('model', 80);
            $table->unsignedSmallInteger('year');

            $table->date('scheduled_date');
            $table->string('scheduled_time', 5);
            $table->string('timezone', 64)->default('America/Monterrey');
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);

            $table->string('service');
            $table->decimal('base_price', 10, 2);
            $table->decimal('tax_rate', 5, 4);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('MXN');

            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['scheduled_date', 'scheduled_time']);
            $table->index(['scheduled_date', 'status']);
            $table->index('status');
            $table->index('email');
        });

        Schema::create('certification_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('scheduled_date');
            $table->string('scheduled_time', 5);
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->timestamps();

            $table->unique(['scheduled_date', 'scheduled_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_slots');
        Schema::dropIfExists('certification_appointments');
    }
};
