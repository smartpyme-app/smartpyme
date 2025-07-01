<?php

namespace App\Services\Authorization;

use App\Services\Authorization\AuthorizationService;
use App\Models\Authorization\AuthorizationType;
use Illuminate\Support\Str;

class AutoAuthorizationService extends AuthorizationService
{
    /**
     * Verificar autorización usando convención: modulo.accion
     */
    public function requiresAuthorizationAuto($module, $action, $data = [])
    {
        $authTypeName = $this->buildAuthorizationName($module, $action);
        return $this->requiresAuthorization($authTypeName, $data);
    }

    /**
     * Solicitar autorización automática
     */
    public function requestAuthorizationAuto($module, $action, $model, $description, $data = [])
    {
        $authTypeName = $this->buildAuthorizationName($module, $action);
        return $this->requestAuthorization($authTypeName, $model, $description, $data);
    }

    /**
     * Construir nombre de autorización: modulo_accion
     */
    private function buildAuthorizationName($module, $action)
    {
        return Str::snake($module) . '_' . Str::snake($action);
    }

    /**
     * Crear tipo de autorización automáticamente si no existe
     */
    public function ensureAuthorizationType($module, $action, $config = [])
    {
        $name = $this->buildAuthorizationName($module, $action);
        
        return AuthorizationType::firstOrCreate(
            ['name' => $name],
            [
                'display_name' => $config['display_name'] ?? ucwords(str_replace('_', ' ', $name)),
                'description' => $config['description'] ?? "Autorización para {$module} - {$action}",
                'conditions' => $config['conditions'] ?? null,
                'expiration_hours' => $config['expiration_hours'] ?? 24,
                'active' => true
            ]
        );
    }
}