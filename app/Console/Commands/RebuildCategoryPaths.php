<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

class RebuildCategoryPaths extends Command
{
    protected $signature = 'categories:rebuild-paths';

    protected $description = 'Recalculează calea materializată (path) pentru tot arborele de categorii';

    public function handle(): int
    {
        $count = Category::rebuildPaths();

        $this->info("Căi recalculate pentru {$count} categorii.");

        return self::SUCCESS;
    }
}
