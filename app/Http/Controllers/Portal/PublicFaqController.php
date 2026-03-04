<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\FAQ;
use Illuminate\View\View;

class PublicFaqController extends Controller
{
    public function index(): View
    {
        $faqs = FAQ::active()->ordered()->get();

        return view('pages.faq', compact('faqs'));
    }
}
