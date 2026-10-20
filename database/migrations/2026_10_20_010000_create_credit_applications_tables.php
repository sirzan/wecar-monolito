<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('calle')->nullable();
            $table->string('colonia')->nullable();
            $table->string('cp', 10)->nullable();
            $table->string('alcaldia')->nullable();
            $table->string('pais')->default('México');
            $table->string('ocupacion')->nullable();
            $table->string('empresa')->nullable();
            $table->string('antiguedad')->nullable();
            $table->unsignedSmallInteger('antiguedad_anios')->nullable();
            $table->decimal('ingreso_mensual', 12, 2)->nullable();
            $table->decimal('deuda_mensual', 12, 2)->nullable();
            $table->decimal('enganche_disponible', 12, 2)->nullable();
            $table->string('banco')->nullable();
            $table->string('tipo_cuenta')->nullable();
            $table->string('tipo_trabajo')->nullable();
            $table->string('genero')->nullable();
        });

        Schema::create('credit_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->foreignUuid('financiera_id')->nullable()->constrained('financieras')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('folio')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->date('birthdate')->nullable();
            $table->string('rfc')->nullable();
            $table->string('curp')->nullable();

            $table->string('calle')->nullable();
            $table->string('colonia')->nullable();
            $table->string('cp', 10)->nullable();
            $table->string('alcaldia')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('pais')->default('México');

            $table->string('ocupacion')->nullable();
            $table->string('empresa')->nullable();
            $table->string('antiguedad')->nullable();
            $table->decimal('ingreso_mensual', 12, 2)->nullable();
            $table->decimal('deuda_mensual', 12, 2)->nullable();
            $table->string('banco')->nullable();
            $table->string('tipo_cuenta')->nullable();

            $table->string('car_brand')->nullable();
            $table->string('car_model')->nullable();
            $table->string('car_version')->nullable();
            $table->unsignedSmallInteger('car_year')->nullable();
            $table->decimal('car_price', 12, 2)->nullable();
            $table->unsignedInteger('car_km')->nullable();

            $table->string('financiera_name')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->string('risk_level')->nullable();
            $table->decimal('down_payment', 12, 2)->nullable();
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->decimal('monthly_payment', 12, 2)->nullable();
            $table->decimal('rate', 5, 2)->nullable();
            $table->decimal('cat', 5, 2)->nullable();
            $table->decimal('total_to_pay', 12, 2)->nullable();

            $table->json('inputs')->nullable();
            $table->json('offers')->nullable();
            $table->string('status')->default('en_revision');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['email', 'created_at']);
        });

        Schema::create('credit_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('credit_application_id')->nullable()->constrained('credit_applications')->cascadeOnDelete();
            $table->string('slot');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'slot']);
            $table->index(['credit_application_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_documents');
        Schema::dropIfExists('credit_applications');

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'calle', 'colonia', 'cp', 'alcaldia', 'pais', 'ocupacion', 'empresa',
                'antiguedad', 'antiguedad_anios', 'ingreso_mensual', 'deuda_mensual',
                'enganche_disponible', 'banco', 'tipo_cuenta', 'tipo_trabajo', 'genero',
            ]);
        });
    }
};
