<?php

namespace Database\Seeders;

use App\Models\Authorization\AuthorizationType;
use App\Models\User;
use App\Models\Admin\Empresa;
use Illuminate\Database\Seeder;

class AuthorizationTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            [
                'name' => 'compras_altas',
                'display_name' => 'Registro de Compras Mayores a $3,000',
                'description' => 'Autorización requerida para registrar compras que superen los $3,000',
                'conditions' => [
                    'amount_threshold' => 3000,
                    'exclude_roles' => ['admin', 'super_admin']
                ],
                'expiration_hours' => 24,
                'active' => true,
                'roles' => ['admin', 'super_admin', 'gerente_compras', 'usuario_supervisor']
            ],
            [
                'name' => 'orden_compra_nivel_1',
                'display_name' => 'Aprobación Orden de Compra $0 - $300',
                'description' => 'Autorización para órdenes de compra de $0 a $300',
                'conditions' => [
                    'min_amount' => 0,
                    'max_amount' => 300,
                    'exclude_roles' => ['admin', 'super_admin']
                ],
                'expiration_hours' => 48,
                'active' => true,
                'roles' => ['asistente_compras', 'gerente_compras', 'admin', 'super_admin']
            ],
            [
                'name' => 'orden_compra_nivel_2',
                'display_name' => 'Aprobación Orden de Compra $300 - $4,999',
                'description' => 'Autorización para órdenes de compra de $300 a $4,999',
                'conditions' => [
                    'min_amount' => 300,
                    'max_amount' => 4999,
                    'exclude_roles' => ['admin', 'super_admin']
                ],
                'expiration_hours' => 72,
                'active' => true,
                'roles' => ['gerente_operaciones', 'gerente_compras', 'admin', 'super_admin']
            ],
            [
                'name' => 'orden_compra_nivel_3',
                'display_name' => 'Aprobación Orden de Compra Mayor a $5,000',
                'description' => 'Autorización para órdenes de compra superiores a $5,000',
                'conditions' => [
                    'min_amount' => 5000,
                    'exclude_roles' => ['admin', 'super_admin']
                ],
                'expiration_hours' => 120,
                'active' => true,
                'roles' => ['gerencia_general', 'admin', 'super_admin']
            ],
            [
                'name' => 'editar_usuario_password',
                'display_name' => 'Modificar Contraseña de Usuario',
                'description' => 'Autorización requerida para cambiar la contraseña de otros usuarios',
                'conditions' => [
                    'exclude_roles' => ['super_admin', 'admin']
                ],
                'expiration_hours' => 12,
                'active' => true,
                'roles' => ['super_admin', 'admin']
            ],
            [
                'name' => 'editar_usuario_rol',
                'display_name' => 'Modificar Rol de Usuario',
                'description' => 'Autorización requerida para cambiar el rol/tipo de un usuario',
                'conditions' => [
                    'exclude_roles' => ['super_admin', 'admin']
                ],
                'expiration_hours' => 24,
                'active' => true,
                'roles' => ['super_admin', 'admin']
            ]
        ];

        // Crear tipos de autorización (una sola vez, son globales)
        foreach ($types as $typeData) {
            $roles = $typeData['roles'];
            unset($typeData['roles']);

            $type = AuthorizationType::updateOrCreate(
                ['name' => $typeData['name']],
                $typeData
            );

            $this->command->info("Tipo '{$type->display_name}' creado/actualizado");
        }

        // Asignar usuarios por empresa
        $this->assignUsersByCompany($types);
    }

    private function assignUsersByCompany($types)
    {
        $empresas = Empresa::all();

        if ($empresas->count() === 0) {
            $this->command->warn("No se encontraron empresas. Asegúrate de tener empresas creadas.");
            return;
        }

        foreach ($empresas as $empresa) {
            $this->command->info("\nProcesando empresa: {$empresa->nombre} (ID: {$empresa->id})");
            
            foreach ($types as $typeData) {
                $type = AuthorizationType::where('name', $typeData['name'])->first();
                $this->assignUsersToAuthorizationType($type, $typeData['roles'], $empresa->id);
            }
        }
    }

    private function assignUsersToAuthorizationType(AuthorizationType $type, array $roles, $empresaId)
    {
        // Buscar usuarios de esta empresa específica con los roles requeridos
        $users = User::where('id_empresa', $empresaId)
            ->whereHas('roles', function($query) use ($roles) {
                $query->whereIn('name', $roles);
            })->get();

        if ($users->count() > 0) {
            // Usar syncWithoutDetaching para no eliminar asignaciones de otras empresas
            $existingIds = $type->users()->pluck('users.id')->toArray();
            $newIds = $users->pluck('id')->toArray();
            $allIds = array_unique(array_merge($existingIds, $newIds));
            
            $type->users()->sync($allIds);
            
            $this->command->info("  ✅ {$users->count()} usuarios asignados a '{$type->display_name}'");
            
            // Mostrar nombres de usuarios asignados
            $userNames = $users->pluck('name')->implode(', ');
            $this->command->info("    👥 Usuarios: {$userNames}");
        } else {
            $this->command->warn("  ❌ Sin usuarios con roles [" . implode(', ', $roles) . "] para '{$type->display_name}'");
            
            // Debug: Mostrar qué usuarios y roles existen en esta empresa
            $allUsers = User::where('id_empresa', $empresaId)->with('roles')->get();
            if ($allUsers->count() > 0) {
                $this->command->info("    🔍 Debug - Usuarios en empresa {$empresaId}:");
                foreach ($allUsers as $user) {
                    $userRoles = $user->roles->pluck('name')->implode(', ');
                    $this->command->info("      - {$user->name}: [{$userRoles}]");
                }
            } else {
                $this->command->warn("    🔍 Debug - No hay usuarios en empresa {$empresaId}");
            }
        }
    }
}