<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_members', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('status');
            $table->foreignUuid('invited_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('agency_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn(['token', 'accepted_at']);
        });
    }
};
