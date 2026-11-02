<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Căi relative pe discul `public` (ex. categories/cabluri.webp).
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_path')->nullable();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
