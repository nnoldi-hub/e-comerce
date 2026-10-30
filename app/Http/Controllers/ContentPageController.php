<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use Illuminate\View\View;

class ContentPageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $page = ContentPage::query()->published()->where('slug', $slug)->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }
}
