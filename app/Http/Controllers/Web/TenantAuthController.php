<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantAuthController extends Controller
{
    public function showLogin(string $companySlug)
    {
        $company = Company::on('mysql')->where('slug', $companySlug)->first();

        if (!$company) {
            abort(404);
        }

        return view('web.auth.tenant-login', [
            'company' => $company,
            'companySlug' => $companySlug,
        ]);
    }

    public function login(Request $request, string $companySlug)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $company = $this->resolveCompany($companySlug);

        if (!$company) {
            return back()->withErrors(['login' => 'Company not found or inactive.'])->withInput($request->only('email'));
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->withInput($request->only('email'));
        }

        $token = $user->createToken('auth-token')->plainTextToken;
        $request->session()->put('web_auth.token', $token);

        $redirectUrl = $company->url ?? url("/{$companySlug}");

        return redirect()->to($redirectUrl);
    }

    protected function resolveCompany(string $companySlug): ?Company
    {
        $company = Company::on('mysql')
            ->where('slug', $companySlug)
            ->where('status', Company::STATUS_ACTIVE)
            ->first();

        if (!$company) {
            return null;
        }

        try {
            $company->makeCurrent();
        } catch (\Exception $e) {
            return null;
        }

        Config::set('database.connections.tenant.database', $company->database);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return $company;
    }
}
