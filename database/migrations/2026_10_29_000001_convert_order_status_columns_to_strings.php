<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statusurile sunt validate de enum-uri PHP. Coloanele enum din MySQL ar cere
 * o migrație pentru fiecare status nou, așa că le mutăm pe varchar (audit ERD #3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->change();
            $table->string('payment_status', 32)->default('pending')->change();
            $table->string('fulfillment_status', 32)->default('unfulfilled')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'awaiting_payment', 'paid', 'processing', 'shipped',
                'delivered', 'completed', 'cancelled', 'returned', 'refunded',
            ])->default('pending')->change();

            $table->enum('payment_status', [
                'pending', 'paid', 'failed', 'refunded', 'partially_refunded',
            ])->default('pending')->change();

            $table->enum('fulfillment_status', [
                'unfulfilled', 'partial', 'fulfilled',
            ])->default('unfulfilled')->change();
        });
    }
};
