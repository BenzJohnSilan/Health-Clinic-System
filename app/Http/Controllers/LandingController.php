<?php

namespace App\Http\Controllers;

use App\Services\LandingPageContent;

/**
 * Public landing page ("/"). Content comes from Admin → Website Management
 * with automatic fallback to the original text (see LandingPageContent).
 * Only public marketing content is passed to the view — never patient,
 * clinical or dashboard data.
 */
class LandingController extends Controller
{
    public function index()
    {
        return view('landing', LandingPageContent::get());
    }
}
