<?php

namespace App\Http\Controllers\Modules\Sales\Web;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TenantAuthController extends Controller
{
    public function showLogin(string $companySlug)
    {
        $company = Company::on('mysql')->where('slug', $companySlug)->first();

        if (!$company) {
            abort(404);
        }

        return view('web.auth.tenant-login-web', [
            'company' => $company,
            'companySlug' => $companySlug,
            'message' => null,
        ]);
    }

    public function login(Request $request, string $companySlug)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return view('web.auth.tenant-login-web', [
                'company' => Company::on('mysql')->where('slug', $companySlug)->first(),
                'companySlug' => $companySlug,
                'message' => null,
            ])->withErrors($validator);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return view('web.auth.tenant-login-web', [
                'company' => Company::on('mysql')->where('slug', $companySlug)->first(),
                'companySlug' => $companySlug,
                'message' => 'The provided credentials are incorrect.',
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;
        $company = Company::current() ?? Company::on('mysql')->where('slug', $companySlug)->first();

        return view('web.auth.tenant-login-success', [
            'message' => 'Login successful.',
            'token' => $token,
            'company' => $company,
            'companySlug' => $companySlug,
        ]);
    }
}
