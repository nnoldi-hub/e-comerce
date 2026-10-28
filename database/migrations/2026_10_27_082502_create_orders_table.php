<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->enum('status', ['pending', 'awaiting_payment', 'paid', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'returned', 'refunded'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            $table->enum('fulfillment_status', ['unfulfilled', 'partial', 'fulfilled'])->default('unfulfilled');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->char('currency', 3)->default('RON');
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('idempotency_key')->unique()->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'placed_at']);
            $table->index(['user_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
