<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('invoice_type')->nullable()->after('color');
            $table->unsignedTinyInteger('owners_count')->nullable()->after('invoice_type');
            $table->string('insurance_status')->nullable()->after('owners_count');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['invoice_type', 'owners_count', 'insurance_status']);
        });
    }
};
