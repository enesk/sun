<?php

namespace App\Http\Controllers\Verwaltung;

use Illuminate\Support\Facades\Auth;

class VerwaltungProfileController extends VerwaltungBaseController
{
    /**
     * User profile — Name, Email, Password, 2FA.
     */
    public function index()
    {
        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Profil'],
        ]);

        $user = Auth::user();

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.profile.index', compact('navigationItems', 'user'));
    }
}
