<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financieras', function (Blueprint $table) {
            $table->string('vehicle_condition')->default('any')->after('tipo');
            $table->json('brand_scope')->nullable()->after('aplica_para');
            $table->unsignedTinyInteger('max_vehicle_age')->nullable()->after('brand_scope');
            $table->boolean('requires_membership')->default(false)->after('max_vehicle_age');
        });

        Schema::table('credit_applications', function (Blueprint $table) {
            $table->decimal('service_fee', 12, 2)->nullable()->after('total_to_pay');
        });
    }

    public function down(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            $table->dropColumn('service_fee');
        });

        Schema::table('financieras', function (Blueprint $table) {
            $table->dropColumn(['vehicle_condition', 'brand_scope', 'max_vehicle_age', 'requires_membership']);
        });
    }
};
