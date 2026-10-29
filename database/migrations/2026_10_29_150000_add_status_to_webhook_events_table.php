<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();

            $table->index(['status', 'created_at'], 'webhook_events_status_created_index');
        });

        DB::table('webhook_events')
            ->whereNotNull('processed_at')
            ->update(['status' => 'processed', 'attempts' => 1]);
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex('webhook_events_status_created_index');
            $table->dropColumn(['status', 'attempts', 'error']);
        });
    }
};
