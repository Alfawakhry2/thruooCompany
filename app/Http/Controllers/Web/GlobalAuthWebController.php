<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\GlobalAuthController as ApiGlobalAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GlobalAuthWebController extends Controller
{
    public function showLogin()
    {
        return view('web.auth.global-login', [
            'companies' => [],
            'email' => '',
            'message' => null,
        ]);
    }

    public function globalLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return view('web.auth.global-login', [
                'companies' => [],
                'email' => $request->input('email', ''),
                'message' => null,
            ])->withErrors($validator);
        }

        $api = app(ApiGlobalAuthController::class);
        $response = $api->globalLogin($request);
        $payload = $response->getData(true);

        if (!($payload['success'] ?? false)) {
            return view('web.auth.global-login', [
                'companies' => [],
                'email' => $request->input('email', ''),
                'message' => $payload['message'] ?? 'Login failed. Please try again.',
            ]);
        }

        $data = $payload['data'] ?? [];

        if (!empty($data['companies']) && empty($data['redirect'])) {
            return view('web.auth.global-login', [
                'companies' => $data['companies'],
                'email' => $request->input('email', ''),
                'message' => $data['message'] ?? 'Select a company to continue.',
            ]);
        }

        return view('web.auth.login-success', [
            'message' => $payload['message'] ?? 'Login successful.',
            'data' => $data,
        ]);
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
            return view('web.auth.global-login', [
                'companies' => [],
                'email' => $request->input('email', ''),
                'message' => null,
            ])->withErrors($validator);
        }

        $api = app(ApiGlobalAuthController::class);
        $response = $api->loginWithCompany($request);
        $payload = $response->getData(true);

        if (!($payload['success'] ?? false)) {
            return view('web.auth.global-login', [
                'companies' => [],
                'email' => $request->input('email', ''),
                'message' => $payload['message'] ?? 'Login failed. Please try again.',
            ]);
        }

        return view('web.auth.login-success', [
            'message' => $payload['message'] ?? 'Login successful.',
            'data' => $payload['data'] ?? [],
        ]);
    }
}
