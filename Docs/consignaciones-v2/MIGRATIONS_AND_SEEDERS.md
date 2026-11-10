# Migraciones y seeders — Consignaciones v2

> Actualizar este archivo al crear cada migración en el repo. En deploy de producción, ejecutar en el orden listado.

## Migraciones (planificadas)

| Orden | Archivo | Qué hace | Rollback |
|-------|---------|----------|----------|
| 1 | `2026_11_11_100000_create_consigna_movimientos_table.php` | Crea tabla ledger consigna | `down` drop table |
| 2 | `2026_11_11_110000_add_inventario_consigna_kardex_permissions.php` | Permisos API/UI kardex consigna (`inventario.consigna_kardex.ver`, exportar) | `down` elimina permisos por nombre |

**Comando local/staging/prod:**

```bash
cd Backend
php artisan migrate
```

Para una sola migración:

```bash
php artisan migrate --path=database/migrations/2026_11_11_100000_create_consigna_movimientos_table.php
```

## Seeders

| Seeder | Cuándo | Comando |
|--------|--------|---------|
| `FuncionalidadesSeeder` | Tras deploy (añade `modulo-consignaciones` al catálogo) | `php artisan db:seed --class=FuncionalidadesSeeder` |

**Nota:** El seeder es idempotente (`updateOrCreate` por slug). No asigna automáticamente la funcionalidad a empresas; eso se hace en **Super Admin → Funcionalidades por empresa**.

## Comandos opcionales post-go-live

```bash
php artisan consigna:ledger-backfill --empresa=ID --dry-run
php artisan consigna:ledger-backfill --empresa=ID
```

(Implementación en Task 17 del plan.)

## Activación manual tras migrate

1. Super Admin: asignar funcionalidad **Módulo de consignaciones** a la empresa piloto.
2. Mi cuenta → Configuración: activar **Ledger de consignaciones**.
3. Roles: conceder `inventario.consigna_kardex.ver` (y permisos consigna compra/venta existentes).
