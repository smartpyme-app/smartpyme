<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionSeeder extends Seeder
{
    private $now;
    private $moduleIds = [];
    private $submoduleIds = [];
    private $permissionIds = [];

    public function __construct()
    {
        $this->now = Carbon::now();
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Limpiar todas las tablas relacionadas
        DB::table('module_permissions')->truncate();
        DB::table('submodules')->truncate();
        DB::table('modules')->truncate();
        Permission::truncate();

        // Lista de módulos principales
        $mainModules = [
            'PERMISSION_ORGANIZACION' => 'Organización',
            'PERMISSION_ADMINISTRACION' => 'Administración',
            'PERMISSION_INTELIGENCIA_NEGOCIOS' => 'Inteligencia de Negocios',
            'PERMISSION_PRODUCTOS' => 'Productos',
            'PERMISSION_SERVICIOS' => 'Servicios',
            'PERMISSION_VENTAS' => 'Ventas',
            'PERMISSION_COMPRAS' => 'Compras',
            'PERMISSION_GASTOS' => 'Gastos',
            'PERMISSION_CITAS' => 'Citas',
            'PERMISSION_FINANZAS' => 'Finanzas',
            'PERMISSION_CONTABILIDAD' => 'Contabilidad',
            'PERMISSION_AYUDA' => 'Ayuda'
        ];

        // Crear módulos y procesar permisos
        foreach ($mainModules as $moduleKey => $displayName) {
            $this->processModule($moduleKey, $displayName);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Procesa un módulo y sus permisos
     */
    private function processModule($moduleKey, $displayName)
    {
        // 1. Crear el módulo
        $moduleId = DB::table('modules')->insertGetId([
            'name' => strtolower(str_replace('PERMISSION_', '', $moduleKey)),
            'display_name' => $displayName,
            'description' => "Módulo de {$displayName}",
            'status' => 1,
            'created_at' => $this->now,
            'updated_at' => $this->now
        ]);

        $this->moduleIds[$moduleKey] = $moduleId;

        // 2. Procesar los permisos del módulo
        $permissions = config("permissions.{$moduleKey}");
        if (!$permissions) {
            return;
        }

        foreach ($permissions as $key => $value) {
            if (is_array($value)) {
                // Es un submódulo
                $this->processSubmodule($moduleId, $key, $value, $moduleKey);
            } else {
                // Es un permiso directo del módulo
                $permissionId = $this->createPermission($value);
                $this->createModulePermission($moduleId, null, $permissionId, explode('.', $value)[1]);
            }
        }
    }

    /**
     * Procesa un submódulo y sus permisos
     */
    private function processSubmodule($moduleId, $submoduleName, $permissions, $moduleKey)
    {
        // 1. Crear el submódulo
        $submoduleId = DB::table('submodules')->insertGetId([
            'module_id' => $moduleId,
            'name' => $submoduleName,
            'display_name' => ucfirst(str_replace('_', ' ', $submoduleName)),
            'description' => "Submódulo de " . ucfirst(str_replace('_', ' ', $submoduleName)),
            'status' => 1,
            'created_at' => $this->now,
            'updated_at' => $this->now
        ]);

        $this->submoduleIds["{$moduleKey}.{$submoduleName}"] = $submoduleId;

        // 2. Procesar los permisos del submódulo
        foreach ($permissions as $permissionName) {
            $permissionId = $this->createPermission($permissionName);
            $this->createModulePermission($moduleId, $submoduleId, $permissionId, explode('.', $permissionName)[2]);
        }
    }

    /**
     * Crea un permiso y retorna su ID
     */
    private function createPermission($name)
    {
        if (isset($this->permissionIds[$name])) {
            return $this->permissionIds[$name];
        }

        $permission = Permission::updateOrCreate(['name' => $name]);
        $this->permissionIds[$name] = $permission->id;
        return $permission->id;
    }

    /**
     * Crea un registro en module_permissions
     */
    private function createModulePermission($moduleId, $submoduleId, $permissionId, $permissionType)
    {
        DB::table('module_permissions')->insert([
            'module_id' => $submoduleId ? null : $moduleId,
            'submodule_id' => $submoduleId,
            'permission_id' => $permissionId,
            'permission_type' => 'base',
            'created_at' => $this->now,
            'updated_at' => $this->now
        ]);
    }
}