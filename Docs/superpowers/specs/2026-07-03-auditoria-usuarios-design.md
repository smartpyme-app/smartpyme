# Auditoría de actividad de usuarios — Design Spec

**Fecha:** 2026-07-03  
**Estado:** Aprobado para implementación

## Decisiones confirmadas

| Decisión | Valor |
|----------|-------|
| Paquete | Laravel Auditing v14 |
| Retención por defecto | **6 meses** (`AUDIT_PURGE_MONTHS=6`) |
| Purga programada | Mensual, día 1 a las 04:00 |

## Problema

SmartPyme no tiene un registro centralizado de acciones de usuarios. Existen piezas parciales (Kardex para movimientos de inventario, `authorizations` para aprobaciones, logs de Laravel en archivo) pero ningún historial legible para administradores del tipo: *"David creó la venta #1234"* o *"Juan actualizó la compra #567"*.

## Objetivos

1. **Admin de empresa:** ver actividad de usuarios de su empresa, filtrable por módulo, usuario y fecha.
2. **Super admin (empresa 2 / 13):** misma vista pero cross-tenant con filtro por empresa.
3. **Menú:** Configuraciones → después de Reportes automáticos.
4. **Retención:** comando Artisan para purgar registros antiguos (default 6 meses), programable en cron.

## No objetivos (v1)

- Auditar lecturas/consultas (GET).
- Reemplazar Kardex como fuente de verdad de cantidades de inventario.
- UI de diff campo-a-campo estilo Git (solo resumen legible + JSON opcional en detalle).
- Auditar los ~55 modelos con scope `empresa` de golpe; rollout por fases.

## Decisión de paquete

**Elegido: [owen-it/laravel-auditing](https://github.com/owen-it/laravel-auditing) v14.x**

| Criterio | Laravel Auditing | Spatie Activity Log |
|----------|------------------|---------------------|
| Laravel 12 + PHP 8.2 | ✅ v14 | ⚠️ v5 exige PHP 8.4 |
| Ya usamos Spatie Permission | — | ✅ mismo vendor |
| Tracking de cambios Eloquent | ✅ core | ✅ vía trait |
| Mensajes tipo "creó venta" | Extensible vía transform | Extensible vía `log()` manual |
| Tabla estándar | `audits` | `activity_log` |

Laravel Auditing encaja mejor con el caso de uso (cambios en modelos de negocio). Spatie Activity Log es superior para eventos libres (`activity()->log('exportó reporte')`) pero v5 sube el piso a PHP 8.4; en v1 no compensa el cambio de stack.

## Arquitectura

```
Modelo Auditable (Venta, Compra, …)
        │ created / updated / deleted
        ▼
Laravel Auditing (trait + resolver JWT)
        │
        ▼
Tabla `audits` extendida
  - id_empresa (index)
  - module (ventas|compras|inventario|…)
  - event, auditable_type/id, old/new values, user_id, ip, url
        │
        ├── GET /auditoria          → Admin empresa (scope automático)
        └── GET /super-admin/auditoria → Super admin (filtro id_empresa)
                │
                ▼
        Angular: AuditoriaComponent (modo tenant | modo platform)
```

### Multi-tenancy

- Columna `id_empresa` en `audits`, poblada en `transformAudit()` desde el modelo auditado o `Auth::user()->id_empresa`.
- Modelo `Audit` custom con global scope `empresa` (mismo patrón que `Venta`).
- Super admin API usa `withoutGlobalScope('empresa')` + middleware `SuperAdmin` + filtro obligatorio `id_empresa` opcional.

### Roles y acceso

| Rol | Empresa | Permiso | Ruta FE |
|-----|---------|---------|---------|
| `admin` | cualquiera | `auditoria.ver` | `/auditoria` |
| `super_admin` | 2 o 13 | `auditoria.ver` + `auditoria.plataforma.ver` | `/admin/auditoria` |

Empresa **2** = tenant SmartPyme (operadores plataforma). Empresa **13** = demo; comparte bypass en middleware `SuperAdmin`.

### Módulos auditables (fases)

**Fase 1 — alto valor, bajo ruido**

| Módulo | Modelos | Eventos |
|--------|---------|---------|
| ventas | `Venta`, `CotizacionVenta`, `OrdenProduccion` | created, updated, deleted |
| compras | `Compra`, `OrdenCompra`, `Gasto` | created, updated, deleted |
| inventario | `Entrada`, `Salida`, `Ajuste`, `Traslado`, `Producto` | created, updated, deleted |
| ajustes | `User`, `Sucursal`, `FormaDePago`, `Impuesto` | created, updated |

**Fase 2:** devoluciones, abonos, clientes/proveedores, contabilidad (partidas), autorizaciones.

**Excluido explícito:** filas `Kardex` (ya es log de movimiento); campos ruidosos (`updated_at`, tokens, hashes).

### Presentación humana (español)

Servicio `AuditPresentationService` mapea:

```text
{usuario} {acción} {tipo} #{correlativo}
→ "David creó Venta #FAC-001234"
```

Acciones: creó / actualizó / eliminó / anuló (si `estado` → anulada).

### Retención

- **Default confirmado: 6 meses** (opción B).
- Comando: `auditoria:purge {--months=6}` (override opcional por flag o env).
- Elimina `audits` con `created_at < now()->subMonths(6)` en chunks de 1000.
- Schedule mensual en `Kernel.php` (día 1, 04:00).
- Config: `config/auditing.php` → `purge_months` desde `AUDIT_PURGE_MONTHS=6`.

### Recomendaciones adicionales (post-v1)

- Export CSV/Excel del listado filtrado.
- Enlace al documento origen si el registro sigue existiendo.
- Auditoría de login fallido / logout (evento custom, no Eloquent).
- Índice compuesto `(id_empresa, module, created_at)`.
- Feature flag por empresa si el volumen crece mucho.

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Volumen de tabla | Purga programada; excluir campos; no auditar Kardex |
| Operaciones bulk sin eventos Eloquent | Hooks en Services críticos (fase 2) |
| Super admin ve data de todas las empresas | Permiso separado + audit log de accesos cross-tenant |
| JWT en jobs/queue sin user | Resolver devuelve null user_id; id_empresa desde modelo |

## Criterios de aceptación

- [ ] Admin empresa ve solo registros de su `id_empresa`.
- [ ] Super admin empresa 2/13 ve todas las empresas con filtro.
- [ ] Filtros: módulo, usuario, rango de fechas (+ empresa en modo platform).
- [ ] Menú en Configuraciones después de Reportes automáticos.
- [ ] Comando purge conserva últimos N meses.
- [ ] Crear venta/compra genera fila legible en auditoría.
