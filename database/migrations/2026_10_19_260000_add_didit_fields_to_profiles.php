<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('didit_session_id')->nullable()->index()->after('metadata');
            $table->string('didit_status')->nullable()->after('didit_session_id');
            $table->timestamp('didit_verified_at')->nullable()->after('didit_status');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['didit_session_id', 'didit_status', 'didit_verified_at']);
        });
    }
};
