<?php

namespace App\Http\Controllers\Api\DteManagement;

use App\Http\Controllers\Controller;
use App\Models\DteManagement\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncLogController extends Controller
{
    /**
     * List sync logs with filters and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = SyncLog::with('userEmailAccount:id,email,provider')
            ->orderBy('started_at', 'desc');

        if ($request->filled('user_email_account_id')) {
            $query->where('user_email_account_id', $request->user_email_account_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);
        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }
}
