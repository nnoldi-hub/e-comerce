<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Valori denormalizate (doar recenzii aprobate): cardurile de produs le citesc fără subinterogări.
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['reviews_count', 'rating_avg']);
        });
    }
};
