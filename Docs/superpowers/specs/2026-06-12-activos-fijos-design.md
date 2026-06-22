# Diseño: Módulo de Activos Fijos — SmartPyme

**Fecha:** 2026-06-12  
**Estado:** Aprobado en brainstorming  
**Alcance:** Operativo + contable-fiscal integrado (opción C)

---

## 1. Contexto y problema

SmartPyme tiene un esqueleto de activos fijos sin implementar:

- Tablas `empresa_activos` y `empresa_activos_categorias` (migraciones 2017)
- Rutas API en `Backend/routes/modulos/contabilidad/activos.php` apuntando a controladores inexistentes
- Enlace de menú admin a `/activos` sin ruta Angular
- Modal `crear-categoria-activo` sin backend
- Tipo de gasto `"Activo Fijo"` que no crea registro de activo
- Categoría de egreso seed `"Depreciaciones"` sin uso automatizado

El inventario (`productos` + kardex) es independiente: bienes de rotación vs activos de uso prolongado.

---

## 2. Objetivos

1. **Operativo:** registrar, ubicar y asignar responsables a activos fijos.
2. **Contable-fiscal:** depreciación automática (línea recta), egresos deducibles, reportes.
3. **Integración:** flujo gastos/compras → activo → depreciación → reportes.
4. **Multi-país:** configuración por país (plantillas) + personalización por empresa, sin reglas hardcodeadas en código.

---

## 3. Decisiones de diseño acordadas

| Tema | Decisión |
|------|----------|
| Alcance | Operativo + contable-fiscal integrado |
| Config fiscal | Genérico configurable multi-país (plantillas Super Admin + categorías empresa) |
| Método depreciación v1 | Solo línea recta |
| Capitalización | Semi-automática: post-guardar gasto/compra → formulario activo pre-llenado |
| Reportes v1 | Libro activos, depreciación período, estado activos, bajas (JSON + Excel + PDF) |
| Bajas v1 | Desecho, venta, transferencia (sin asientos partidas en v1) |
| Permisos | Granulares por acción |
| Partidas contables | Egresos en v1; hooks para partidas cuando backend exista |

---

## 4. Arquitectura

### 4.1 Stack y patrones existentes

- **Backend:** Laravel 8, JWT, MySQL, Maatwebsite Excel, DomPDF
- **Frontend:** Angular 15, lazy loading bajo `contabilidad`
- **Patrones a replicar:** `PresupuestosController`, libros IVA (`LibrosIVAController` + Exports + Blade), planilla (`EmpresaConfiguracionPlanilla` + plantillas por país), funcionalidades (`Funcionalidad` slug + Super Admin)

### 4.2 Diagrama de flujo end-to-end

```
Gasto (Activo Fijo) ──┐
Compra (línea AF)   ──┼──► Activo confirmado ──► Cronograma depreciación
Alta manual         ──┘                              │
                                                     ▼
                                          Corrida mensual ──► Egreso "Depreciaciones"
                                                     │
                                                     ▼
                                          Reportes (libro, estado, bajas)
```

### 4.3 Estructura de archivos (nuevos)

```
Backend/
├── app/Models/Contabilidad/
│   ├── Activo.php
│   ├── ActivoCategoria.php
│   ├── ActivoDepreciacion.php
│   ├── ActivoMovimiento.php
│   ├── PaisActivoCategoriaPlantilla.php
│   └── EmpresaActivosConfiguracion.php
├── app/Services/Contabilidad/
│   └── DepreciacionService.php
├── app/Http/Controllers/Api/Contabilidad/Activos/
│   ├── ActivosController.php
│   ├── CategoriasController.php
│   ├── DepreciacionController.php
│   ├── ActivosReportesController.php
│   └── PaisPlantillasController.php          # Super Admin
├── app/Exports/Contabilidad/Activos/
│   ├── LibroActivosExport.php
│   ├── DepreciacionPeriodoExport.php
│   ├── EstadoActivosExport.php
│   └── BajasPeriodoExport.php
├── database/migrations/
│   └── 2026_*_extend_activos_fijos_tables.php
└── resources/views/reportes/contabilidad/activos/
    ├── libro-activos.blade.php
    ├── depreciacion-periodo.blade.php
    ├── estado-activos.blade.php
    └── bajas-periodo.blade.php

Frontend/src/app/views/contabilidad/activos/
├── activos.component.ts/html              # Listado
├── activo/activo.component.ts/html        # Crear/editar/detalle
├── categorias/                            # CRUD categorías empresa
├── configuracion/                         # Config general empresa
├── depreciacion/                          # Corrida mensual
└── reportes/                              # 4 reportes con tabs
```

### 4.4 Rutas

**API** (existente `activos.php`, completar controladores):

```
GET    /api/activos
POST   /api/activo
GET    /api/activo/{id}
POST   /api/activos/filtrar
DELETE /api/activo/{id}
GET    /api/activos/categorias
POST   /api/activos/categoria
DELETE /api/activos/categoria/{id}
POST   /api/activos/depreciacion/preview
POST   /api/activos/depreciacion/ejecutar
POST   /api/activo/{id}/baja
GET    /api/activos/reportes/{tipo}
GET    /api/activos/reportes/{tipo}/descargar
```

**Angular** (`contabilidad.routing.module.ts`):

```
/contabilidad/activos
/contabilidad/activo/crear
/contabilidad/activo/editar/:id
/contabilidad/activo/crear?egreso_id=X
/contabilidad/activo/crear?compra_detalle_id=X
/contabilidad/activos/categorias
/contabilidad/activos/configuracion
/contabilidad/activos/depreciacion
/contabilidad/activos/reportes
```

Corregir menú admin: `/activos` → `/contabilidad/activos`.

---

## 5. Modelo de datos

### 5.1 Extensión `empresa_activos_categorias`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `plantilla_id` | int nullable FK | Origen plantilla país |
| `metodo_depreciacion` | enum | `linea_recta` (único en v1) |
| `porcentaje_anual` | decimal(5,2) | Configurable |
| `vida_util_anios` | decimal(5,2) | Explícito o derivado de % |
| `valor_residual_default` | decimal(9,2) | Default 0 |
| `permite_bien_usado` | boolean | |
| `reglas_bien_usado` | json nullable | Tabla años → % precio nuevo |

### 5.2 Extensión `empresa_activos`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `egreso_id` | int nullable FK | Origen gasto |
| `compra_detalle_id` | int nullable FK | Origen línea compra |
| `depreciacion_acumulada` | decimal(9,2) | |
| `valor_en_libros` | decimal(9,2) | valor_compra - acumulada |
| `valor_residual` | decimal(9,2) | |
| `es_usado` | boolean | |
| `porcentaje_base_usado` | decimal(5,2) nullable | |
| `fecha_inicio_depreciacion` | date nullable | |
| `estado_registro` | enum | `borrador`, `activo`, `baja` |

Renombrar uso de `deprecicion` → depreciación acumulada en lógica nueva (no renombrar columna en v1 para evitar migración destructiva; mapear en modelo).

Estados operativos existentes: `En uso`, `Desechado`, `En reparación`.

### 5.3 Nueva `empresa_activos_depreciaciones`

| Campo | Tipo |
|-------|------|
| `id`, `activo_id`, `empresa_id` | |
| `periodo` | char(7) `YYYY-MM` |
| `monto` | decimal(9,2) |
| `depreciacion_acumulada` | decimal(9,2) |
| `valor_en_libros` | decimal(9,2) |
| `estado` | `pendiente`, `aplicada`, `cancelada` |
| `egreso_id` | int nullable |
| timestamps | |

Unique: `(activo_id, periodo)`.

### 5.4 Nueva `empresa_activos_movimientos`

| Campo | Tipo |
|-------|------|
| `activo_id`, `empresa_id`, `usuario_id` | |
| `tipo` | `alta`, `depreciacion`, `transferencia`, `reparacion`, `baja`, `venta` |
| `fecha`, `descripcion` | |
| `monto` | decimal nullable |
| `metadata` | json nullable |
| timestamps | |

### 5.5 Nueva `pais_activos_categorias_plantilla`

| Campo | Tipo |
|-------|------|
| `cod_pais` | char(3) — SV, HN, etc. |
| `nombre` | string |
| `metodo_depreciacion` | enum |
| `porcentaje_anual` | decimal |
| `vida_util_anios` | decimal |
| `permite_bien_usado` | boolean |
| `reglas_bien_usado` | json nullable |
| `activo` | boolean |
| timestamps | |

### 5.6 Nueva `empresa_activos_configuracion`

| Campo | Tipo |
|-------|------|
| `empresa_id` | FK unique |
| `frecuencia` | `mensual` (v1) |
| `dia_corte` | int default 1 |
| `redondeo_decimales` | int default 2 |
| timestamps | |

### 5.7 Extensión gastos y compras

**Tabla `egresos` (Gasto):**

- `activo_id` int nullable
- `pendiente_capitalizacion` boolean default false

**Tabla detalle compra** (identificar tabla real en codebase al implementar):

- `es_activo_fijo` boolean
- `activo_id` int nullable
- `pendiente_capitalizacion` boolean

---

## 6. Configuración multi-país

### Nivel 1 — Plantillas (Super Admin)

- CRUD en `/super-admin/activos-fijos/plantillas/{cod_pais}`
- Permiso: `superadmin.activos.plantillas`
- Seed inicial vacío o documentado; Super Admin configura por país

### Nivel 2 — Categorías empresa

- Al activar módulo o crear empresa: copiar plantillas del `cod_pais` de la empresa → `empresa_activos_categorias`
- Contador edita en `/contabilidad/activos/categorias`
- Permiso: `contabilidad.activos.categorias`

### Nivel 3 — Activo individual

- Hereda categoría; override puntual: bien usado, valor residual

Patrón referencia: `ConfiguracionPlanillaController` + `EmpresaConfiguracionPlanilla`.

---

## 7. Motor de depreciación (línea recta)

### Fórmula

```
valor_depreciable = valor_base - valor_residual
cuota_mensual = valor_depreciable / (vida_util_anios * 12)
```

`valor_base` = `valor_compra`, ajustado por reglas bien usado si aplica.

### Generación cronograma

Trigger: confirmar activo (`estado_registro = activo`).

1. Calcular cuota mensual.
2. Insertar N filas en `empresa_activos_depreciaciones` (estado `pendiente`).
3. Última cuota absorbe redondeo.

### Corrida mensual

1. `POST /activos/depreciacion/preview` — lista activos y montos del período.
2. `POST /activos/depreciacion/ejecutar` — transacción DB:
   - Marcar líneas `aplicada`
   - Crear egreso(s) categoría `"Depreciaciones"`
   - Actualizar `depreciacion_acumulada`, `valor_en_libros`
   - Insertar movimiento tipo `depreciacion`
3. Bloquear re-ejecución si período ya tiene líneas `aplicada`.

### DepreciacionService

```php
generarCronograma(Activo $activo): Collection
calcularCuotaMensual(Activo $activo): float
ajustarPorBienUsado(Activo $activo): float
previewCorrida(int $empresaId, string $periodo): array
ejecutarCorrida(int $empresaId, string $periodo, int $usuarioId): CorridaResult
```

---

## 8. Capitalización semi-automática

### Desde gasto

1. Usuario guarda gasto con tipo `"Activo Fijo"`.
2. Backend marca `pendiente_capitalizacion = true`.
3. Frontend redirige a `/contabilidad/activo/crear?egreso_id={id}`.
4. Pre-llenado: nombre, valor_compra, fecha_compra, sucursal_id, referencia.
5. Usuario completa categoría, serie, responsable → confirma.
6. Backend: `activo.estado_registro = activo`, vincula `egreso_id`, genera cronograma, `egreso.capitalizado = true`, `egreso.activo_id = activo.id`.

### Desde compra

- Checkbox `es_activo_fijo` por línea de detalle.
- Una línea → redirección directa al formulario activo.
- Varias líneas → pantalla batch (una fila editable por línea) antes de confirmar en lote.

### Estados vínculo

```
pendiente_capitalizacion → activo_borrador → capitalizado
```

Validaciones:

- No capitalizar dos veces mismo documento/línea.
- Gasto/compra anulado → bloquear capitalización.
- Valor activo default = valor documento; override solo con permiso `contabilidad.activos.crear`.

---

## 9. Bajas y transferencias

### Tipos

| Tipo | Efecto v1 |
|------|-----------|
| Desecho | Estado `Desechado`, cancelar depreciaciones pendientes, movimiento `baja` |
| Venta | Igual + campo `monto_venta`; sin asiento ganancia/pérdida en v1 |
| Transferencia | Cambio responsable/ubicación/sucursal; movimiento `transferencia`; no baja contable |

### API

`POST /api/activo/{id}/baja` — body: tipo, fecha, motivo, monto_venta opcional.

Permiso: `contabilidad.activos.baja`.

---

## 10. Reportes

Patrón: `LibrosIVAController` + Export class + Blade PDF.

| Reporte | Filtros | Formatos |
|---------|---------|----------|
| Libro de activos | inicio, fin, sucursal, categoría | json, xlsx, pdf |
| Depreciación período | mes, año, sucursal | json, xlsx, pdf |
| Estado de activos | fecha_corte, sucursal, categoría | json, xlsx, pdf |
| Bajas del período | inicio, fin, sucursal | json, xlsx, pdf |

Permiso lectura: `contabilidad.activos.ver`.

---

## 11. Permisos

| Clave | Descripción |
|-------|-------------|
| `contabilidad.activos.ver` | Ver módulo y reportes |
| `contabilidad.activos.crear` | CRUD activos |
| `contabilidad.activos.capitalizar` | Flujo desde gasto/compra |
| `contabilidad.activos.depreciar` | Ejecutar corrida |
| `contabilidad.activos.baja` | Dar de baja / vender |
| `contabilidad.activos.categorias` | Categorías empresa |
| `contabilidad.activos.configuracion` | Config empresa |
| `superadmin.activos.plantillas` | Plantillas por país |

**Funcionalidad módulo:** slug `modulo-activos-fijos` en tabla `funcionalidades`; activación por empresa vía Super Admin.

**Defaults por rol:**

- Administrador: todos
- Supervisor: ver, crear, capitalizar, categorias
- Contador: ver, depreciar, baja, categorias, reportes
- Otros: sin acceso

Frontend: `canEditTest` / `canCreateTest` (patrón partidas). Backend: checks en acciones críticas. Fallback temporal: `canEdit()` / `canCreate()` por rol si permisos granulares no están cableados aún.

---

## 12. Integración contable (egresos)

- Corrida depreciación crea registro en `egresos` con categoría `"Depreciaciones"` (seed existente en `EmpresaTableSeeder`).
- **Un egreso consolidado por corrida** (no uno por activo). El desglose por activo queda en `empresa_activos_depreciaciones.egreso_id` apuntando al mismo egreso, y el detalle en movimientos/notas del egreso.
- Hooks futuros: campos `partida_id` nullable en depreciación y activo.

---

## 13. Fases de implementación

| Fase | Entregable | Dependencias |
|------|-----------|--------------|
| **1 — Fundación** | Migraciones, modelos, ActivosController, CategoriasController, UI listado/formulario, permisos base, funcionalidad slug, menú corregido | — |
| **2 — Config + depreciación** | Plantillas país (Super Admin), config empresa, DepreciacionService, corrida, egresos automáticos | Fase 1 |
| **3 — Capitalización** | Cambios gasto + compra, flujo semi-automático, estados pendiente | Fase 1 |
| **4 — Bajas + reportes** | Baja API, 4 exports, 4 blades, UI reportes | Fases 2, 3 |
| **5 — Futuro** | Partidas, prorrateo primer mes, saldos decrecientes, reversiones | Post v1 |

Estimación total v1: **8–10 semanas** (1 desarrollador).

---

## 14. Fuera de alcance v1

- Método saldos decrecientes
- Prorrateo mes parcial de adquisición
- Asientos contables debe/haber (partidas)
- Reversión de corrida de depreciación
- Mantenimiento preventivo (EAM)
- Conversión producto inventario → activo fijo
- Múltiples libros contable vs fiscal
- Importación masiva desde Excel

---

## 15. Criterios de aceptación

1. Usuario con permiso crea activo manual, confirma, ve cronograma generado.
2. Gasto tipo Activo Fijo redirige a formulario pre-llenado; al confirmar queda vinculado y no capitalizable de nuevo.
3. Línea compra marcada AF capitaliza igual que gasto.
4. Corrida mensual genera egresos Depreciaciones y actualiza valores en libros.
5. Baja cancela depreciaciones futuras y aparece en reporte bajas.
6. Cuatro reportes exportan Excel y PDF.
7. Super Admin configura plantillas por `cod_pais`; empresa copia al activar módulo.
8. Empresa sin funcionalidad `modulo-activos-fijos` no accede al módulo.
9. Menú `/contabilidad/activos` funcional; rutas API no retornan 500 por clases faltantes.

---

## 16. Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Backend partidas inexistente | Egresos en v1; hooks preparados |
| Typo columna `deprecicion` | Mapear en modelo; no renombrar en v1 |
| Tabla detalle compra desconocida | Identificar en fase 3 antes de migrar |
| Permisos granulares incompletos en plataforma | Fallback rol + implementar claves progresivamente |
| Reglas fiscales incorrectas por país | Responsabilidad Super Admin al configurar plantillas |

---

## 17. Referencias en codebase

- Migraciones: `Backend/database/migrations/2017_10_12_100007_create_empresa_activos*.php`
- Rutas: `Backend/routes/modulos/contabilidad/activos.php`
- Patrón CRUD: `Backend/app/Http/Controllers/Api/Contabilidad/PresupuestosController.php`
- Patrón reportes: `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php`
- Patrón config país: `Backend/app/Http/Controllers/Api/Planilla/ConfiguracionPlanillaController.php`
- Gasto Activo Fijo: `Frontend/src/app/views/compras/gastos/gasto/gasto.component.ts`
- Modal categoría: `Frontend/src/app/shared/modals/crear-categoria-activo/`
- Menú roto: `Frontend/src/app/layout/header/admin/admin-header.component.html`
