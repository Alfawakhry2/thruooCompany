<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Modules\Sales\Api\TenantRegistrationController as ApiTenantRegistrationController;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function showRegister()
    {
        $api = app(ApiTenantRegistrationController::class);
        $response = $api->getOptions();
        $payload = $response->getData(true);
        $data = $payload['data'] ?? [];

        return view('web.auth.register', [
            'industries' => $data['industries'] ?? [],
            'staffCounts' => $data['staff_counts'] ?? [],
            'modules' => $data['modules'] ?? [],
            'plans' => $data['plans'] ?? [],
        ]);
    }

    public function register(Request $request)
    {
        $api = app(ApiTenantRegistrationController::class);
        $response = $api->register($request);
        $payload = $response->getData(true);

        if (($payload['success'] ?? false) === true) {
            $data = $payload['data'] ?? [];

            if (!empty($data['token'])) {
                $request->session()->put('web_auth.token', $data['token']);
            }

            $redirectUrl = $data['redirect']['url'] ?? null;

            return $redirectUrl
                ? redirect()->to($redirectUrl)
                : redirect()->route('web.login')->with('status', $payload['message'] ?? 'Registration successful.');
        }

        if (!empty($payload['errors']) && is_array($payload['errors'])) {
            return back()->withErrors($payload['errors'])->withInput();
        }

        $message = $payload['message'] ?? 'Registration failed. Please try again.';

        return back()->withErrors(['register' => $message])->withInput();
    }
}
