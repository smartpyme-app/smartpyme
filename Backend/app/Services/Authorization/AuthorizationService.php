<?php

namespace App\Services\Authorization;

use App\Models\Authorization\Authorization;
use App\Models\Authorization\AuthorizationType;
use App\Models\User;
use App\Mail\Authorization\AuthorizationRequest;
use App\Events\AuthorizationApproved;
use App\Events\AuthorizationRejected;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AuthorizationService
{
    public function requiresAuthorization($typeName, $data = [])
    {
        $type = AuthorizationType::where('name', $typeName)
            ->where('active', true)
            ->first();

        if (!$type) return false;

        return $type->evaluateConditions($data);
    }

    public function requestAuthorization($typeName, $model, $description, $data = [])
    {
        $type = AuthorizationType::where('name', $typeName)->first();
        
        if (!$type) {
            throw new \Exception("Tipo de autorización '{$typeName}' no encontrado");
        }

        // Solo verificar autorizaciones pendientes si el modelo tiene ID real
        if (is_object($model) && property_exists($model, 'id') && $model->id > 0) {
            if ($model->hasPendingAuthorization($typeName)) {
                throw new \Exception("Ya existe una autorización pendiente para esta acción");
            }
        }

        $authorization = Authorization::create([
            'authorization_type_id' => $type->id,
            'authorizeable_type' => is_object($model) && get_class($model) !== 'stdClass' ? get_class($model) : 'App\Models\Compras\Compra',
            'authorizeable_id' => is_object($model) ? ($model->id ?? 0) : 0,
            'requested_by' => auth()->id(),
            'description' => $description,
            'data' => $data,
            'expires_at' => now()->addHours($type->expiration_hours),
            'status' => 'pending'
        ]);

        $this->notifyAuthorizers($authorization);
        return $authorization;
    }

    public function approveAuthorization($code, $authorizationCode, $notes = null)
    {

        Log::info($code);
        $authorization = Authorization::where('code', $code)
            ->pending()
            ->first();

        Log::info($authorization);

        Log::info($authorizationCode);
        if (!$authorization) {
            throw new \Exception("Autorización no encontrada o ya procesada");
        }

        // Verificar código de autorización del usuario
        $user = auth()->user();
        Log::info($user);
        if (!$this->verifyUserAuthorizationCode($user, $authorizationCode)) {
            throw new \Exception("Código de autorización incorrecto");
        }

        // Verificar que el usuario puede autorizar este tipo
        if (!$this->canUserAuthorize($user, $authorization->authorizationType)) {
            throw new \Exception("No tienes permisos para esta autorización");
        }

        $authorization->update([
            'status' => 'approved',
            'authorized_by' => $user->id,
            'authorized_at' => now(),
            'notes' => $notes
        ]);

        event(new AuthorizationApproved($authorization));

        return $authorization;
    }

    public function rejectAuthorization($code, $authorizationCode, $notes = null)
    {
        $authorization = Authorization::where('code', $code)
            ->pending()
            ->first();

        if (!$authorization) {
            throw new \Exception("Autorización no encontrada o ya procesada");
        }

        // Verificar código de autorización del usuario
        $user = auth()->user();
        if (!$this->verifyUserAuthorizationCode($user, $authorizationCode)) {
            throw new \Exception("Código de autorización incorrecto");
        }

        if (!$this->canUserAuthorize($user, $authorization->authorizationType)) {
            throw new \Exception("No tienes permisos para esta autorización");
        }

        $authorization->update([
            'status' => 'rejected',
            'authorized_by' => $user->id,
            'authorized_at' => now(),
            'notes' => $notes
        ]);

        event(new AuthorizationRejected($authorization));

        return $authorization;
    }

    public function getPendingAuthorizationsForUser($userId)
    {
        $user = User::findOrFail($userId);
        
        return Authorization::pending()
            ->where('id_empresa', $user->id_empresa) 
            ->whereHas('authorizationType.users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->with(['authorizationType', 'requester', 'authorizeable'])
            ->get();
    }

    public function expireOldAuthorizations()
    {
        Authorization::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    private function notifyAuthorizers(Authorization $authorization)
    {
        Mail::to("joseespana94@gmail.com")->send(
            new AuthorizationRequest($authorization, auth()->user())
        );
    }

    // private function notifyAuthorizers(Authorization $authorization)
    // {
    //     // Solo notificar usuarios de la misma empresa
    //     $authorizers = $authorization->authorizationType->users()
    //         ->where('id_empresa', $authorization->id_empresa)
    //         ->get();

    //     foreach ($authorizers as $authorizer) {
    //         Mail::to($authorizer->email)->send(
    //             new AuthorizationRequest($authorization, $authorizer)
    //         );
    //     }
    // }

    private function canUserAuthorize(User $user, AuthorizationType $type)
    {
        return $user->authorizationTypes()->where('authorization_types.id', $type->id)->exists();
    }

    private function verifyUserAuthorizationCode($user, $authorizationCode)
    {
        $userCode = $user->codigo_autorizacion;
        $result = (string)$userCode === (string)$authorizationCode;
        
        return $result;
    }
    
    public function getAuthorizationHistory($model)
    {
        return $model->authorizations()
            ->with(['authorizationType', 'requester', 'authorizer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}