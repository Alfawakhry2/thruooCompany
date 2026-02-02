<?php

namespace App\Http\Controllers\Modules\Sales\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Modules\Sales\Api\TenantRegistrationController as ApiTenantRegistrationController;
use Illuminate\Http\Request;

class TenantRegistrationController extends Controller
{
    public function showRegister()
    {
        $options = $this->getOptionsData();

        return view('web.auth.registration', [
            'industries' => $options['industries'],
            'staffCounts' => $options['staff_counts'],
            'modules' => $options['modules'],
            'plans' => $options['plans'],
            'form' => [],
            'message' => null,
        ]);
    }

    public function register(Request $request)
    {
        $api = app(ApiTenantRegistrationController::class);
        $response = $api->register($request);
        $payload = $response->getData(true);

        if (($payload['success'] ?? false) === true) {
            return view('web.auth.register-success', [
                'message' => $payload['message'] ?? 'Registration successful.',
                'data' => $payload['data'] ?? [],
            ]);
        }

        $options = $this->getOptionsData();

        if (!empty($payload['errors']) && is_array($payload['errors'])) {
            return view('web.auth.registration', [
                'industries' => $options['industries'],
                'staffCounts' => $options['staff_counts'],
                'modules' => $options['modules'],
                'plans' => $options['plans'],
                'form' => $request->all(),
                'message' => $payload['message'] ?? null,
            ])->withErrors($payload['errors']);
        }

        return view('web.auth.registration', [
            'industries' => $options['industries'],
            'staffCounts' => $options['staff_counts'],
            'modules' => $options['modules'],
            'plans' => $options['plans'],
            'form' => $request->all(),
            'message' => $payload['message'] ?? 'Registration failed. Please try again.',
        ]);
    }

    protected function getOptionsData(): array
    {
        $api = app(ApiTenantRegistrationController::class);
        $response = $api->getOptions();
        $payload = $response->getData(true);
        $data = $payload['data'] ?? [];

        return [
            'industries' => $data['industries'] ?? [],
            'staff_counts' => $data['staff_counts'] ?? [],
            'modules' => $data['modules'] ?? [],
            'plans' => $data['plans'] ?? [],
        ];
    }
}
