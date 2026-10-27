<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('audience')->default('user')->after('type');
            $table->index('audience');
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->nullable()->after('plan')->constrained('plans')->nullOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignUuid('agency_id')->nullable()->after('car_id')->constrained('agencies')->nullOnDelete();
            $table->index(['agency_id', 'status']);
        });

        DB::table('plans')->where('key_name', 'agencia')->update(['audience' => 'agency']);
        DB::table('agencies')->whereNotNull('plan')->orderBy('id')->each(function ($agency) {
            $planId = DB::table('plans')->where('key_name', $agency->plan)->value('id');

            if ($planId) {
                DB::table('agencies')->where('id', $agency->id)->update(['plan_id' => $planId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'status']);
            $table->dropConstrainedForeignId('agency_id');
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });
    }
};
