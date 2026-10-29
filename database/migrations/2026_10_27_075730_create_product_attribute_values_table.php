<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();

            $table->unique(
                ['product_id', 'attribute_value_id', 'product_variant_id'],
                'pav_product_value_variant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
