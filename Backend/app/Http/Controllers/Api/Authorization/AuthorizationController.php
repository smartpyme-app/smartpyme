<?php

namespace App\Http\Controllers\Api\Authorization;

use App\Http\Controllers\Controller;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\PendingRecordService;
use App\Models\Authorization\Authorization;
use App\Models\Authorization\AuthorizationType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use App\Http\Requests\Authorization\ApproveAuthorizationRequest;
use App\Http\Requests\Authorization\RejectAuthorizationRequest;
use App\Http\Requests\Authorization\RequestAuthorizationRequest;
use App\Http\Requests\Authorization\CheckRequirementRequest;

class AuthorizationController extends Controller
{
    protected $authorizationService;
    protected $pendingRecordService;

    public function __construct(
        AuthorizationService $authorizationService,
        PendingRecordService $pendingRecordService
    ) {
        $this->authorizationService = $authorizationService;
        $this->pendingRecordService = $pendingRecordService;
    }

    public function index(Request $request)
    {
        $query = Authorization::with(['authorizationType', 'requester', 'authorizer'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate($request->paginate ?? 15));
    }

    public function pending()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }
        
        $authorizations = $this->authorizationService
            ->getPendingAuthorizationsForUser($user->id);

        return response()->json([
            'ok' => true,
            'data' => $authorizations
        ]);
    }

    public function approve(ApproveAuthorizationRequest $request, $code)
    {

        try {
            $authorization = $this->authorizationService->approveAuthorization(
                $code,
                $request->authorization_code,
                $request->notes
            );

            return response()->json([
                'ok' => true,
                'message' => 'Autorización aprobada exitosamente',
                'data' => $authorization
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function reject(RejectAuthorizationRequest $request, $code)
    {

        try {
            $authorization = $this->authorizationService->rejectAuthorization(
                $code,
                $request->authorization_code,
                $request->notes
            );

            return response()->json([
                'ok' => true,
                'message' => 'Autorización rechazada',
                'data' => $authorization
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function show($code)
    {
        $authorization = Authorization::where('code', $code)
            ->with(['authorizationType', 'requester', 'authorizer', 'authorizeable'])
            ->first();

        if (!$authorization) {
            return response()->json([
                'ok' => false,
                'message' => 'Autorización no encontrada'
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $authorization
        ]);
    }

    public function request(RequestAuthorizationRequest $request)
    {
       try {
           if ($request->model_id) {
               $modelClass = $request->model_type;
               $model = $modelClass::findOrFail($request->model_id);
           } else {
               $model = new \stdClass();
               $model->id = 0;
           }

           $authorization = $this->authorizationService->requestAuthorization(
               $request->type,
               $model,
               $request->description,
               $request->data ?? []
           );

            // Si es compras_altas, SIEMPRE crear compra pendiente
            if ($request->type === 'compras_altas') {
                Log::info('Creating pending purchase for compras_altas...');
                $result = $this->pendingRecordService->createCompraPendiente(
                    $request->data,
                    $request->type,
                    $request->description,
                    $request->data ?? []
                );
                
                return response()->json([
                    'ok' => true,
                    'message' => 'Compra creada pendiente de autorización',
                    'data' => $result['authorization'],
                    'compra' => $result['compra'],
                    'estado' => $result['estado']
                ]);
            }

            // Si es orden de compra, crear orden pendiente
            if (str_starts_with($request->type, 'orden_compra_nivel_')) {
                Log::info('Creating pending orden compra for ' . $request->type);
                $result = $this->pendingRecordService->createOrdenCompraPendiente(
                    $request->data,
                    $request->type,
                    $request->description,
                    $request->data ?? [],
                    $authorization
                );
                
                return response()->json([
                    'ok' => true,
                    'message' => 'Orden de compra creada pendiente de autorización',
                    'data' => $result['authorization'],
                    'orden' => $result['orden'],
                    'estado' => $result['estado']
                ]);
            }

            if (str_starts_with($request->type, 'editar_usuario_')) {
                $result = $this->pendingRecordService->handleUserPendingChanges(
                    $request->data['id_usuario'],
                    $request->type,
                    $request->data,
                    $authorization
                );
                
                return response()->json([
                    'ok' => true,
                    'message' => 'Cambio pendiente de autorización',
                    'data' => $result['authorization'],
                    'pending_changes' => $result['pending_changes']
                ]);
            }
    
           return response()->json([
               'ok' => true,
               'message' => 'Autorización solicitada exitosamente',
               'data' => $authorization
           ]);
       } catch (\Exception $e) {
           Log::error('Authorization request error: ' . $e->getMessage());
           return response()->json([
               'ok' => false,
               'message' => $e->getMessage()
           ], 400);
       }
    }


    public function checkRequirement(CheckRequirementRequest $request)
    {

        $required = $this->authorizationService->requiresAuthorization(
            $request->type,
            $request->data ?? []
        );

        return response()->json([
            'ok' => true,
            'requires_authorization' => $required
        ]);
    }

    public function history($modelType, $modelId)
    {
        $modelClass = urldecode($modelType);
        $model = $modelClass::findOrFail($modelId);

        $history = $this->authorizationService->getAuthorizationHistory($model);

        return response()->json([
            'ok' => true,
            'data' => $history
        ]);
    }
}