<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexuri pentru interogările din rapoarte și dashboard (SalesReport,
 * export CSV, widget-uri). Fiecare index se adaugă doar dacă lipsește, deci
 * migrarea e sigură și pe baze în care a fost creat deja unul manual.
 */
return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> tabel => nume index => coloane */
    private const INDEXES = [
        'orders' => [
            // vânzări: WHERE status IN (...) AND placed_at BETWEEN ...
            'orders_status_placed_at_index' => ['status', 'placed_at'],
            // export CSV și comenzi pe zile: WHERE placed_at BETWEEN ...
            'orders_placed_at_index' => ['placed_at'],
        ],
        'payments' => [
            // încasat: WHERE status IN (...) AND paid_at BETWEEN ...
            'payments_status_paid_at_index' => ['status', 'paid_at'],
        ],
        'refunds' => [
            // rambursat: WHERE status = 'succeeded' AND processed_at BETWEEN ...
            'refunds_status_processed_at_index' => ['status', 'processed_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }
};
