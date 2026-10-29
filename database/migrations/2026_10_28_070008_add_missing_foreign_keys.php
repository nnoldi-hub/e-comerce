<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            // pentru verificarea max_uses_per_user
            $table->index(['coupon_id', 'user_id']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropIndex(['coupon_id', 'user_id']);
            $table->dropForeign(['order_id']);
        });
    }
};
