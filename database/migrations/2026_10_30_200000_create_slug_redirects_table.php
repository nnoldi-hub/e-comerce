<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabelul din scheletul inițial (from/to/status_code) nu a fost folosit de
        // niciun cod; este înlocuit de structura bazată pe tip + țintă.
        Schema::dropIfExists('slug_redirects');

        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('old_key');
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['type', 'old_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
