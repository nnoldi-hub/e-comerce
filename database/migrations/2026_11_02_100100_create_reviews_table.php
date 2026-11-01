<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('verified_purchase')->default(false);
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            // Un client poate lăsa o singură recenzie per produs (o nouă trimitere o înlocuiește).
            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
