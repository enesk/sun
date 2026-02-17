<?php

namespace App\Http\Middleware;

use App\Models\Portal\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $company = Company::ownedBy(Auth::id())->first();

        if (! $company) {
            return redirect()->route('portal.companies.create')
                ->with('info', 'Bitte tragen Sie zuerst Ihre Firma ein.');
        }

        // Share company with all views in this request
        view()->share('ownerCompany', $company);
        $request->attributes->set('ownerCompany', $company);

        return $next($request);
    }
}
