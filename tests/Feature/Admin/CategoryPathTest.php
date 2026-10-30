<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use InvalidArgumentException;

class CategoryPathTest extends AdminTestCase
{
    public function test_path_is_built_from_ancestor_slugs(): void
    {
        $root = Category::create(['name' => 'Electronice']);
        $mid = Category::create(['name' => 'Telefoane', 'parent_id' => $root->id]);
        $leaf = Category::create(['name' => 'Accesorii', 'parent_id' => $mid->id]);

        $this->assertSame('electronice', $root->refresh()->path);
        $this->assertSame('electronice/telefoane', $mid->refresh()->path);
        $this->assertSame('electronice/telefoane/accesorii', $leaf->refresh()->path);
    }

    public function test_moving_a_category_updates_its_descendants(): void
    {
        $a = Category::create(['name' => 'Casa']);
        $b = Category::create(['name' => 'Gradina']);
        $child = Category::create(['name' => 'Scule', 'parent_id' => $a->id]);
        $grandchild = Category::create(['name' => 'Ciocane', 'parent_id' => $child->id]);

        $child->update(['parent_id' => $b->id]);

        $this->assertSame('gradina/scule', $child->refresh()->path);
        $this->assertSame('gradina/scule/ciocane', $grandchild->refresh()->path);
    }

    public function test_a_category_cannot_become_its_own_descendant(): void
    {
        $a = Category::create(['name' => 'A']);
        $b = Category::create(['name' => 'B', 'parent_id' => $a->id]);

        $this->expectException(InvalidArgumentException::class);

        $a->update(['parent_id' => $b->id]);
    }

    public function test_rebuild_paths_repairs_missing_values(): void
    {
        $a = Category::create(['name' => 'Radacina']);
        $b = Category::create(['name' => 'Frunza', 'parent_id' => $a->id]);
        Category::query()->update(['path' => null]);

        $this->artisan('categories:rebuild-paths')->assertSuccessful();

        $this->assertSame('radacina/frunza', $b->refresh()->path);
    }
}
