<?php

namespace Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SP-2339: permisos anular (desde eliminar), partidas por origen, FE emitir, ver costos inventario.
 * Idempotente; no truncar. Copia eliminar → anular en roles que ya tenían eliminar (módulos operativos).
 */
class Sp2339RolesPermisosSeeder extends Seeder
{
    /** Prefijos de permiso donde aplica anular (convive con eliminar deprecado). */
    private const ANULAR_PREFIXES = [
        'ventas.',
        'compras.',
        'productos.',
        'servicios.',
        'gastos.',
    ];

    private const MODULE_KEYS = [
        'PERMISSION_VENTAS' => 'Ventas',
        'PERMISSION_COMPRAS' => 'Compras',
        'PERMISSION_PRODUCTOS' => 'Productos',
        'PERMISSION_SERVICIOS' => 'Servicios',
        'PERMISSION_GASTOS' => 'Gastos',
        'PERMISSION_CONTABILIDAD' => 'Contabilidad',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->registrarPermisosDesdeConfig();
        $this->call(VentasSolicitudesCompraPermissionSeeder::class);
        $this->sincronizarAnularDesdeEliminar();
        $this->copiarEliminarAAnularEnRoles();
        $this->asignarPartidasGranularesDesdeCrear();
        $this->asignarSolicitudesCompraDesdeCotizaciones();
        $this->revocarSolicitudesCompraVentasLimitado();
        $this->aplicarMatrizRolesSp2343();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function registrarPermisosDesdeConfig(): void
    {
        foreach (self::MODULE_KEYS as $configKey => $displayName) {
            $tree = config("permissions.{$configKey}");
            if (! is_array($tree)) {
                continue;
            }

            $moduleName = strtolower(str_replace('PERMISSION_', '', $configKey));
            $module = Module::firstOrCreate(
                ['name' => $moduleName],
                [
                    'display_name' => $displayName,
                    'description' => "Módulo de {$displayName}",
                    'status' => 1,
                ]
            );

            $this->registrarNodo($module, null, $tree);
        }
    }

    /** @param array<string, mixed> $tree */
    private function registrarNodo(Module $module, ?Submodule $submodule, array $tree): void
    {
        foreach ($tree as $key => $value) {
            if (is_array($value)) {
                $sub = Submodule::firstOrCreate(
                    [
                        'module_id' => $module->id,
                        'name' => $key,
                    ],
                    [
                        'display_name' => ucfirst(str_replace('_', ' ', $key)),
                        'description' => 'Submódulo de '.ucfirst(str_replace('_', ' ', $key)),
                        'status' => 1,
                    ]
                );
                $this->registrarNodo($module, $sub, $value);

                continue;
            }

            $permission = Permission::firstOrCreate(['name' => $value, 'guard_name' => 'web']);
            ModulePermission::firstOrCreate(
                [
                    'module_id' => $submodule ? null : $module->id,
                    'submodule_id' => $submodule?->id,
                    'permission_id' => $permission->id,
                ],
                ['permission_type' => 'base']
            );
        }
    }

    private function sincronizarAnularDesdeEliminar(): void
    {
        $eliminar = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', '%.eliminar')
            ->get();

        foreach ($eliminar as $perm) {
            if (! $this->permisoEnAlcanceAnular($perm->name)) {
                continue;
            }

            $anularName = preg_replace('/\.eliminar$/', '.anular', $perm->name);
            if ($anularName === $perm->name) {
                continue;
            }

            $anular = Permission::firstOrCreate(['name' => $anularName, 'guard_name' => 'web']);

            $links = ModulePermission::query()
                ->where('permission_id', $perm->id)
                ->get();

            foreach ($links as $link) {
                ModulePermission::firstOrCreate(
                    [
                        'module_id' => $link->module_id,
                        'submodule_id' => $link->submodule_id,
                        'permission_id' => $anular->id,
                    ],
                    ['permission_type' => 'base']
                );
            }
        }
    }

    private function copiarEliminarAAnularEnRoles(): void
    {
        $map = $this->mapaEliminarAAnular();
        if ($map === []) {
            return;
        }

        $roles = Role::query()->where('guard_name', 'web')->get();
        foreach ($roles as $role) {
            foreach ($map as $eliminar => $anular) {
                if ($role->hasPermissionTo($eliminar) && ! $role->hasPermissionTo($anular)) {
                    $role->givePermissionTo($anular);
                }
            }
        }

        // Permisos directos en model_has_permissions
        foreach ($map as $eliminar => $anular) {
            $eliminarId = Permission::query()->where('name', $eliminar)->value('id');
            $anularId = Permission::query()->where('name', $anular)->value('id');
            if (! $eliminarId || ! $anularId) {
                continue;
            }

            $modelType = config('permission.models.user', 'App\Models\User');
            $rows = DB::table('model_has_permissions')
                ->where('permission_id', $eliminarId)
                ->where('model_type', $modelType)
                ->get();

            foreach ($rows as $row) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $anularId,
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                ]);
            }
        }
    }

    /** @return array<string, string> eliminar => anular */
    private function mapaEliminarAAnular(): array
    {
        $map = [];
        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', '%.eliminar')
            ->pluck('name')
            ->each(function (string $name) use (&$map): void {
                if (! $this->permisoEnAlcanceAnular($name)) {
                    return;
                }
                $anular = preg_replace('/\.eliminar$/', '.anular', $name);
                if ($anular !== $name) {
                    $map[$name] = $anular;
                }
            });

        return $map;
    }

    private function permisoEnAlcanceAnular(string $name): bool
    {
        foreach (self::ANULAR_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Quien tenía partidas.crear recibe los generar_* de fase 1. */
    private function asignarPartidasGranularesDesdeCrear(): void
    {
        $crear = config('permissions.PERMISSION_CONTABILIDAD.partidas.crear');
        $granulares = [
            config('permissions.PERMISSION_CONTABILIDAD.partidas.generar_venta'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.generar_cxc'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.generar_compra'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.generar_cxp'),
            config('permissions.PERMISSION_CONTABILIDAD.partidas.generar_gasto'),
        ];

        foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
            if (! $role->hasPermissionTo($crear)) {
                continue;
            }
            foreach ($granulares as $perm) {
                if ($perm && ! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    /** Roles con cotizaciones de ventas reciben solicitudes cross-empresa (excepto ventas_limitado). */
    private function asignarSolicitudesCompraDesdeCotizaciones(): void
    {
        $cotVer = config('permissions.PERMISSION_VENTAS.cotizaciones.ver');
        $solicitudPerms = $this->permisosSolicitudesCompra();
        if (! $cotVer || $solicitudPerms === []) {
            return;
        }

        $ventasLimitado = config('constants.ROL_VENTAS_LIMITADO', 'ventas_limitado');

        foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
            if ($role->name === $ventasLimitado) {
                continue;
            }
            if (! $role->hasPermissionTo($cotVer)) {
                continue;
            }
            foreach ($solicitudPerms as $perm) {
                if ($perm && ! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    private function revocarSolicitudesCompraVentasLimitado(): void
    {
        $role = Role::findByName(config('constants.ROL_VENTAS_LIMITADO', 'ventas_limitado'), 'web');
        if (! $role) {
            return;
        }
        foreach ($this->permisosSolicitudesCompra() as $perm) {
            if ($perm && $role->hasPermissionTo($perm)) {
                $role->revokePermissionTo($perm);
            }
        }
    }

    /** @return list<string|null> */
    private function permisosSolicitudesCompra(): array
    {
        $cfg = config('permissions.PERMISSION_VENTAS.solicitudes');

        return is_array($cfg) ? array_values($cfg) : [];
    }

    /** Ajustes SP-2343 sobre plantillas (delta, no sync total). */
    private function aplicarMatrizRolesSp2343(): void
    {
        $emitirFe = config('permissions.PERMISSION_VENTAS.facturacion_electronica.emitir');
        $verCostos = config('permissions.PERMISSION_PRODUCTOS.inventario.ver_costos');

        $contadorSuperior = Role::findByName(config('constants.ROL_CONTADOR_SUPERIOR', 'contador_superior'), 'web');
        $contadorAuxiliar = Role::findByName(config('constants.ROL_CONTADOR_AUXILIAR', 'contador_auxiliar'), 'web');
        $supervisorLimitado = Role::findByName(config('constants.ROL_SUPERVISOR_LIMITADO', 'supervisor_limitado'), 'web');
        $usuarioVentas = Role::findByName(config('constants.ROL_USUARIO_VENTAS', 'usuario_ventas'), 'web');
        $ventasLimitado = Role::findByName(config('constants.ROL_VENTAS_LIMITADO', 'ventas_limitado'), 'web');

        if ($supervisorLimitado && $verCostos && $supervisorLimitado->hasPermissionTo($verCostos)) {
            $supervisorLimitado->revokePermissionTo($verCostos);
        }

        foreach ([$contadorSuperior, $contadorAuxiliar] as $contador) {
            if (! $contador) {
                continue;
            }
            if ($emitirFe && $contador->hasPermissionTo($emitirFe)) {
                $contador->revokePermissionTo($emitirFe);
            }
        }

        if ($usuarioVentas && $emitirFe && ! $usuarioVentas->hasPermissionTo($emitirFe)) {
            $usuarioVentas->givePermissionTo($emitirFe);
        }
        if ($ventasLimitado && $emitirFe && ! $ventasLimitado->hasPermissionTo($emitirFe)) {
            $ventasLimitado->givePermissionTo($emitirFe);
        }

        // Contador: partidas granulares sí; quitar crear genérico en auxiliar si solo debe ver
        if ($contadorAuxiliar) {
            $crear = config('permissions.PERMISSION_CONTABILIDAD.partidas.crear');
            if ($crear && $contadorAuxiliar->hasPermissionTo($crear)) {
                $contadorAuxiliar->revokePermissionTo($crear);
            }
        }
    }
}
