<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('min_subtotal')->default(0);
            $table->unsignedBigInteger('max_subtotal')->nullable();
            $table->unsignedBigInteger('price');
            $table->timestamps();

            $table->index(['shipping_method_id', 'min_subtotal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
