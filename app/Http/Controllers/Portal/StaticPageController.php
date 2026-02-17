<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function impressum(): View
    {
        return view('pages.impressum');
    }

    public function datenschutz(): View
    {
        return view('pages.datenschutz');
    }
}
