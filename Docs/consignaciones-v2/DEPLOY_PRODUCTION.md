# Deploy a producción — Consignaciones v2

> Completar con URLs y versiones exactas al release. Revisar con DevOps antes del primer piloto.

## 1. Pre-deploy

- [ ] Backup base de datos (completo).
- [ ] Ventana acordada con cliente piloto (si aplica).
- [ ] Tag/release branch mergeado a producción.

## 2. Backend

```bash
cd Backend
composer install --no-dev --optimize-autoloader
php artisan migrate --path=database/migrations/2026_11_11_100000_create_consigna_movimientos_table.php --force
php artisan migrate --path=database/migrations/2026_11_11_110000_add_inventario_consigna_kardex_permissions.php --force
php artisan db:seed --class=FuncionalidadesSeeder --force
php artisan route:clear
php artisan config:cache
php artisan route:cache
```

### Migraciones esperadas (ver `MIGRATIONS_AND_SEEDERS.md` para lista actualizada)

- `create_consigna_movimientos_table`
- `add_inventario_consigna_kardex_permissions`

## 3. Frontend

- Build producción (`ng build` / pipeline CI habitual).
- Desplegar artefactos estáticos.

## 4. Post-deploy (solo empresa piloto)

1. Super Admin → Funcionalidades → activar **Módulo de consignaciones** en empresa X.
2. Admin empresa X → Mi cuenta → activar **Ledger de consignaciones**.
3. Asignar permisos `inventario.consigna_kardex.ver` y consignas compra/venta al rol operativo.
4. Ejecutar checklist `QA_MANUAL.md` secciones 2–6 en producción (datos de prueba mínimos).

## 5. Rollback

| Escenario | Acción |
|-----------|--------|
| Bug lógico ledger | Desactivar `consignaciones_ledger_activo` en Mi cuenta; desasignar funcionalidad en Super Admin. Operación vuelve a legacy sin revertir código. |
| Migración fallida | Restaurar backup BD; no ejecutar `down` en prod salvo tabla vacía. |
| Revert código | Deploy commit anterior; **no** borrar tabla `consigna_movimientos` si ya hay datos (conservar para auditoría). |

## 6. Monitoreo

- Sentry / logs: errores `ConsignaMovimientoService`.
- Consultas soporte: “inventario duplicado” → verificar flag ledger y liquidación.

## 7. Backfill histórico (opcional)

Solo si negocio lo pide y tras UAT:

```bash
php artisan consigna:ledger-backfill --empresa=ID --dry-run
```
