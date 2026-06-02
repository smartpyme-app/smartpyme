<?php

namespace App\Http\Controllers\Api\DteManagement;

use App\Http\Controllers\Controller;
use App\Services\Gmail\GmailOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GmailAuthController extends Controller
{
    public function __construct(
        protected GmailOAuthService $gmailOAuthService
    ) {
    }

    /**
     * Get the Gmail OAuth2 authorization URL.
     * Requires authenticated user.
     *
     * @return JsonResponse
     */
    public function redirect(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $url = $this->gmailOAuthService->getAuthorizationUrl(
            $user->id,
            $user->id_empresa
        );

        return response()->json(['url' => $url]);
    }

    /**
     * Handle the OAuth2 callback from Google.
     * Called by Google redirect - no JWT required.
     * Redirects to frontend with success/error query params.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function callback(Request $request): RedirectResponse
    {
        $frontendUrl = rtrim(config('services.gmail.frontend_redirect', 'http://localhost:4200'), '/');
        $redirectPath = '/dte-management/cuentas';

        try {
            $request->validate([
                'code' => 'required|string',
                'state' => 'required|string',
            ]);

            $account = $this->gmailOAuthService->handleCallback(
                $request->input('code'),
                $request->input('state')
            );

            $url = $frontendUrl . $redirectPath . '?gmail=success&email=' . urlencode($account->email);
            return redirect()->away($url);
        } catch (\InvalidArgumentException $e) {
            return redirect()->away($frontendUrl . $redirectPath . '?gmail=error&message=' . urlencode('Estado de autorización inválido'));
        } catch (\RuntimeException $e) {
            return redirect()->away($frontendUrl . $redirectPath . '?gmail=error&message=' . urlencode($e->getMessage()));
        }
    }
}
