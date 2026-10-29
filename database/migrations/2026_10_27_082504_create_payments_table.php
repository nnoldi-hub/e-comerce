<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_reference')->nullable();
            $table->string('method', 32)->nullable();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('RON');
            $table->string('status', 32)->default('pending');
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_reference']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
