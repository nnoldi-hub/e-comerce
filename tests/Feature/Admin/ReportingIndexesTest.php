<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Schema;

class ReportingIndexesTest extends AdminTestCase
{
    public function test_the_report_queries_have_their_indexes(): void
    {
        foreach ([
            'orders' => ['orders_status_placed_at_index', 'orders_placed_at_index'],
            'payments' => ['payments_status_paid_at_index'],
            'refunds' => ['refunds_status_processed_at_index'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->assertTrue(Schema::hasIndex($table, $index), "Lipsește indexul {$index} de pe {$table}.");
            }
        }
    }
}
