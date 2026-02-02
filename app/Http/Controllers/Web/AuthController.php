<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\GlobalAuthController as ApiGlobalAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        return view('web.auth.login', [
            'companies' => $request->session()->get('web_auth.companies', []),
            'email' => $request->session()->get('web_auth.email', ''),
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only('email'));
        }

        $api = app(ApiGlobalAuthController::class);
        $response = $api->globalLogin($request);
        $payload = $response->getData(true);

        if (!($payload['success'] ?? false)) {
            return $this->backWithApiError($payload, $request);
        }

        $data = $payload['data'] ?? [];

        if (!empty($data['companies']) && empty($data['redirect'])) {
            $request->session()->put('web_auth.companies', $data['companies']);
            $request->session()->put('web_auth.email', $request->email);
            return redirect()->route('web.login')->with('status', $data['message'] ?? 'Select a company to continue.');
        }

        $request->session()->forget('web_auth.companies');
        $request->session()->forget('web_auth.email');

        if (!empty($data['token'])) {
            $request->session()->put('web_auth.token', $data['token']);
        }

        $redirectUrl = $data['redirect']['url'] ?? $data['redirect']['legacy_url'] ?? null;

        return $redirectUrl
            ? redirect()->to($redirectUrl)
            : redirect()->route('web.login')->with('status', $payload['message'] ?? 'Login successful.');
    }

    public function loginWithCompany(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required_without:company_slug|string',
            'company_slug' => 'required_without:company_id|string',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only('email'));
        }

        $api = app(ApiGlobalAuthController::class);
        $response = $api->loginWithCompany($request);
        $payload = $response->getData(true);

        if (!($payload['success'] ?? false)) {
            return $this->backWithApiError($payload, $request);
        }

        $data = $payload['data'] ?? [];
        $request->session()->forget('web_auth.companies');
        $request->session()->forget('web_auth.email');

        if (!empty($data['token'])) {
            $request->session()->put('web_auth.token', $data['token']);
        }

        $redirectUrl = $data['redirect']['url'] ?? $data['redirect']['legacy_url'] ?? null;

        return $redirectUrl
            ? redirect()->to($redirectUrl)
            : redirect()->route('web.login')->with('status', $payload['message'] ?? 'Login successful.');
    }

    protected function backWithApiError(array $payload, Request $request)
    {
        if (!empty($payload['errors']) && is_array($payload['errors'])) {
            return back()->withErrors($payload['errors'])->withInput($request->only('email'));
        }

        $message = $payload['message'] ?? 'Login failed. Please try again.';

        return back()->withErrors(['login' => $message])->withInput($request->only('email'));
    }
}
