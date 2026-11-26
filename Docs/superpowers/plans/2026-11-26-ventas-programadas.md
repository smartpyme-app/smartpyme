# Facturas Programadas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Módulo Ventas → Facturas Programadas: plantillas propias con frecuencia mensual/anual, hora, emisión opcional, email al emitir, historial de ejecuciones y FK en `ventas`, sin tocar recurrentes.

**Architecture:** Tablas `ventas_programadas` + `detalles_venta_programada` + `ventas_programadas_ejecuciones`. CRUD API + UI Angular. Cron cada 15 min clona plantilla a `ventas` (borrador o emitida reutilizando el camino de `GenerarVentasRecurrentesService` / `FacturacionService`). Calendario en `ProgramacionCalendario` (día 31 → último día del mes).

**Tech Stack:** Laravel (migrations, models, command, Kernel), Angular (módulo ventas), PHPUnit.

**Spec:** `Docs/superpowers/specs/2026-11-26-ventas-programadas-design.md`

## Global Constraints

- No modificar comportamiento de ventas recurrentes ni su cron `ventas:generar-recurrentes`.
- Frecuencia MVP: solo `mensual` | `anual`.
- Guardar plantilla no crea venta.
- Email al cliente solo si `emitir_automatico` y `enviar_email`.
- Precio con vigencia: fuera de alcance.
- UI copy: **Facturas Programadas**; rutas `ventas/programadas` / API `ventas-programadas`.
- Scheduler: cada 15 minutos, `withoutOverlapping()`.
- Tras error: historial `error` + avanzar `proxima_ejecucion` (no loop).
- Idempotencia por `periodo` único en ejecuciones `ok`.

## File map

| Archivo | Rol |
|---------|-----|
| `Backend/database/migrations/*_create_ventas_programadas_tables.php` | Schema plantilla + detalles + ejecuciones |
| `Backend/database/migrations/*_add_id_venta_programada_to_ventas_table.php` | FK en ventas |
| `Backend/app/Models/Ventas/VentaProgramada.php` | Plantilla |
| `Backend/app/Models/Ventas/DetalleVentaProgramada.php` | Líneas |
| `Backend/app/Models/Ventas/VentaProgramadaEjecucion.php` | Historial |
| `Backend/app/Models/Ventas/Venta.php` | + `id_venta_programada` |
| `Backend/app/Services/Ventas/ProgramacionCalendario.php` | próxima fecha / período |
| `Backend/app/Services/Ventas/EjecutarVentasProgramadasService.php` | Runtime |
| `Backend/app/Console/Commands/EjecutarVentasProgramadas.php` | Artisan |
| `Backend/app/Console/Kernel.php` | schedule 15 min |
| `Backend/app/Http/Controllers/Api/Ventas/VentaProgramadaController.php` | CRUD |
| `Backend/app/Http/Requests/Ventas/StoreVentaProgramadaRequest.php` | Validación create |
| `Backend/app/Http/Requests/Ventas/UpdateVentaProgramadaRequest.php` | Validación update |
| `Backend/routes/modulos/ventas/ventas-programadas.php` | Rutas |
| `Backend/routes/api.php` | require rutas |
| `Backend/tests/Unit/Services/Ventas/ProgramacionCalendarioTest.php` | Calendario |
| `Backend/tests/Unit/Services/Ventas/EjecutarVentasProgramadasServiceTest.php` | Runtime (o check PHP sin DB si el resto del módulo usa ese patrón) |
| `Frontend/.../ventas/programadas/*` | Listado + form + ver/historial |
| `Frontend/.../ventas.routing.module.ts` | Ruta |
| `Frontend/.../ventas.component.html` (+ abonos/recurrentes/devoluciones tabs) | Botón pestaña |

---

### Task 1: `ProgramacionCalendario` (TDD)

**Files:**
- Create: `Backend/app/Services/Ventas/ProgramacionCalendario.php`
- Test: `Backend/tests/Unit/Services/Ventas/ProgramacionCalendarioTest.php`

**Interfaces:**
- Produces:
  - `ProgramacionCalendario::periodo(string $frecuencia, Carbon|string $cuando): string` — mensual `Y-m`, anual `Y`
  - `ProgramacionCalendario::calcularProxima(string $frecuencia, int $dia, string $hora, Carbon $desde): Carbon` — primera ejecución ≥ `$desde` (o estrictamente siguiente ciclo según flag)
  - `ProgramacionCalendario::siguienteTrasEjecucion(string $frecuencia, int $dia, string $hora, Carbon $ejecutadaEn): Carbon` — +1 mes/año, día inválido → último día del mes, misma hora

- [ ] **Step 1: Write failing tests**

```php
<?php
namespace Tests\Unit\Services\Ventas;

use App\Services\Ventas\ProgramacionCalendario;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ProgramacionCalendarioTest extends TestCase
{
    public function test_periodo_mensual_y_anual(): void
    {
        $this->assertSame('2026-03', ProgramacionCalendario::periodo('mensual', '2026-03-11 08:00:00'));
        $this->assertSame('2026', ProgramacionCalendario::periodo('anual', '2026-03-11 08:00:00'));
    }

    public function test_dia_31_en_febrero_usa_ultimo_dia(): void
    {
        $desde = Carbon::parse('2026-01-31 08:00:00', 'America/El_Salvador');
        $next = ProgramacionCalendario::siguienteTrasEjecucion('mensual', 31, '08:00:00', $desde);
        $this->assertSame('2026-02-28 08:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_calcular_proxima_hoy_si_aun_no_paso_la_hora(): void
    {
        $ahora = Carbon::parse('2026-03-11 07:00:00', 'America/El_Salvador');
        $p = ProgramacionCalendario::calcularProxima('mensual', 11, '08:00:00', $ahora);
        $this->assertSame('2026-03-11 08:00:00', $p->format('Y-m-d H:i:s'));
    }

    public function test_calcular_proxima_mes_siguiente_si_ya_paso(): void
    {
        $ahora = Carbon::parse('2026-03-11 09:00:00', 'America/El_Salvador');
        $p = ProgramacionCalendario::calcularProxima('mensual', 11, '08:00:00', $ahora);
        $this->assertSame('2026-04-11 08:00:00', $p->format('Y-m-d H:i:s'));
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
cd Backend && php artisan test --filter=ProgramacionCalendarioTest
```

Expected: class not found / FAIL

- [ ] **Step 3: Implement `ProgramacionCalendario`** (timezone app default / `America/El_Salvador` consistente con otros crons)

- [ ] **Step 4: Run tests — expect PASS**

- [ ] **Step 5: Commit** `test: calendario facturas programadas`

---

### Task 2: Migrations + models

**Files:**
- Create: `Backend/database/migrations/2026_11_26_120000_create_ventas_programadas_tables.php`
- Create: `Backend/database/migrations/2026_11_26_120100_add_id_venta_programada_to_ventas_table.php`
- Create: `Backend/app/Models/Ventas/VentaProgramada.php`
- Create: `Backend/app/Models/Ventas/DetalleVentaProgramada.php`
- Create: `Backend/app/Models/Ventas/VentaProgramadaEjecucion.php`
- Modify: `Backend/app/Models/Ventas/Venta.php` (fillable + relación)

**Schema `ventas_programadas`:**  
`id`, `id_empresa`, `nombre`, `id_cliente`, `id_documento`, `id_sucursal` nullable, `id_bodega` nullable, `id_vendedor` nullable, `forma_pago` nullable, `condicion` nullable, totales espejo mínimos (`sub_total`, `descuento`, `iva`, `total`, `gravada`, `exenta`, `no_sujeta` nullable decimals), `observaciones` nullable, `frecuencia` enum/string, `dia_ejecucion` tinyint, `hora_ejecucion` time, `proxima_ejecucion` datetime indexed, `activo` bool default true, `emitir_automatico` bool default false, `enviar_email` bool default false, `max_ejecuciones` unsignedInt nullable, `ejecuciones_count` unsignedInt default 0, `id_usuario` nullable, timestamps. Index `(id_empresa, activo, proxima_ejecucion)`.

**Schema `detalles_venta_programada`:**  
Campos alineados a `Detalle` fillable (sin `id_venta`): `id_venta_programada`, `id_producto`, `id_presentacion`, `descripcion`, `cantidad`, `precio`, `precio_sin_iva`, `precio_con_iva`, `costo`, `descuento`, `sub_total`, `tipo_gravado`, `no_sujeta`, `exenta`, `gravada`, `cuenta_a_terceros`, `total_costo`, `total`, `id_vendedor`, `iva`, `porcentaje_impuesto`, `fe_cr_exoneracion` json nullable.

**Schema `ventas_programadas_ejecuciones`:**  
`id_venta_programada`, `id_venta` nullable, `periodo` string, `programada_para` datetime, `ejecutada_en` datetime nullable, `estado` string, `modo` string nullable, `mensaje_error` text nullable, timestamps. Unique (`id_venta_programada`, `periodo`, `estado`) **no** — usar unique parcial solo para ok es difícil en MySQL; en servicio: before insert ok, `where periodo + estado=ok` exists check; índice `(id_venta_programada, periodo)`.

**`ventas`:** `id_venta_programada` nullable FK `nullOnDelete`.

- [ ] **Step 1: Escribir migrations y models** con relaciones `detalles()`, `ejecuciones()`, `cliente()`, `documento()`, `empresa()`; en `Venta` relación `ventaProgramada()`.
- [ ] **Step 2: `php artisan migrate`** en entorno local (o dry-read migrations).
- [ ] **Step 3: Commit** `feat: schema facturas programadas`

---

### Task 3: CRUD API

**Files:**
- Create: `Backend/app/Http/Controllers/Api/Ventas/VentaProgramadaController.php`
- Create: `Backend/app/Http/Requests/Ventas/StoreVentaProgramadaRequest.php`
- Create: `Backend/app/Http/Requests/Ventas/UpdateVentaProgramadaRequest.php`
- Create: `Backend/routes/modulos/ventas/ventas-programadas.php`
- Modify: `Backend/routes/api.php` (require tras `ventas.php`)

**Endpoints (auth empresa, same middleware que ventas):**

| Método | Ruta | Acción |
|--------|------|--------|
| GET | `/ventas-programadas` | index + `q` search nombre/cliente |
| POST | `/ventas-programadas` | store |
| GET | `/ventas-programadas/{id}` | show + detalles |
| PUT | `/ventas-programadas/{id}` | update (recalcula `proxima_ejecucion` si cambian día/hora/frecuencia) |
| PATCH | `/ventas-programadas/{id}/activo` | body `{activo: bool}` |
| GET | `/ventas-programadas/{id}/ejecuciones` | historial paginado |

**Store/Update rules:** `nombre` required, `id_cliente` exists, `id_documento` exists, `frecuencia` in mensual|anual, `dia_ejecucion` 1-31, `hora_ejecucion` required, `detalles` array min 1, `max_ejecuciones` nullable integer min 1, flags boolean.

Al crear/actualizar: set `proxima_ejecucion = ProgramacionCalendario::calcularProxima(...)`. Scope siempre `id_empresa` del usuario.

- [ ] **Step 1: Requests + controller + routes**
- [ ] **Step 2: Probar manualmente con un POST/GET** (o feature test mínimo si el proyecto ya tiene patrón similar)
- [ ] **Step 3: Commit** `feat: API facturas programadas`

---

### Task 4: Servicio de ejecución + command + schedule

**Files:**
- Create: `Backend/app/Services/Ventas/EjecutarVentasProgramadasService.php`
- Create: `Backend/app/Console/Commands/EjecutarVentasProgramadas.php`
- Modify: `Backend/app/Console/Kernel.php` (junto a recurrentes, **sin** cambiar recurrentes)
- Test: `Backend/tests/Unit/Services/Ventas/ProgramacionCalendarioTest.php` ya cubre calendario; añadir test de idempotencia/periodo en servicio si se puede sin DB pesada — mínimo: método puro de “debeEjecutar” o documentar check en Task 1 extendido.

**Command:** `ventas:ejecutar-programadas` → `EjecutarVentasProgramadasService::ejecutar(Carbon $now)`.

**Kernel:**

```php
$schedule->command('ventas:ejecutar-programadas')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ventas-programadas.log'));
```

**Service flow (por fila locked):**
1. Skip si `!activo` o max alcanzado.
2. `periodo = ProgramacionCalendario::periodo(...)`.
3. Si existe ejecución `ok` mismo periodo → avanzar próxima y continue.
4. Crear `Venta` + `Detalle` desde plantilla; `id_venta_programada`; estado borrador/Pendiente según convención del proyecto para no emitidas.
5. Si `emitir_automatico`: reutilizar lógica de emisión de `GenerarVentasRecurrentesService` (extraer helper privado compartido **solo si** es diff mínimo; si no, duplicar el tramo `emitir`/clone de forma consciente y acotada — preferir llamar métodos existentes públicos/reutilizables del `FacturacionService` como ya hace recurrentes). Email cliente solo si `enviar_email`.
6. Historial ok/error; count++; `proxima_ejecucion = siguienteTrasEjecucion`; desactivar si max.

**Importante:** no cambiar `GenerarVentasRecurrentesService` salvo extracción optativa sin cambio de comportamiento (si se extrae, cubrir con test existente `RecurrenciaCalendarioTest` / smoke).

- [ ] **Step 1: Implement service + command**
- [ ] **Step 2: Registrar schedule**
- [ ] **Step 3: `php artisan ventas:ejecutar-programadas` dry-run con 0 filas OK**
- [ ] **Step 4: Commit** `feat: cron facturas programadas`

---

### Task 5: Frontend listado + navegación

**Files:**
- Create: `Frontend/src/app/views/ventas/programadas/programadas.component.ts`
- Create: `Frontend/src/app/views/ventas/programadas/programadas.component.html`
- Create: `Frontend/src/app/views/ventas/programadas/programadas.component.scss` (si el módulo usa scss)
- Modify: `Frontend/src/app/views/ventas/ventas.routing.module.ts`
- Modify tab buttons in:
  - `Frontend/src/app/views/ventas/ventas.component.html`
  - `Frontend/src/app/views/ventas/recurrentes/recurrentes.component.html`
  - `Frontend/src/app/views/ventas/abonos/abonos-ventas.component.html`
  - `Frontend/src/app/views/ventas/devoluciones/devoluciones-ventas.component.html`

**Ruta:** `{ path: 'ventas/programadas', canActivate: [AdminGuard], component: ProgramadasComponent, title: 'Facturas Programadas' }` **después** de recurrentes.

**Listado columnas:** Nombre, Cliente, Tipo documento, Frecuencia, Próxima ejecución, Activo, Enviar email, Ejecuciones, acciones (ver, editar, ⋯ activo).

Botón **Crear Factura Programada** → ruta crear.

- [ ] **Step 1: Scaffold component + route + tabs**
- [ ] **Step 2: Wire GET list + search**
- [ ] **Step 3: Commit** `feat: UI listado facturas programadas`

---

### Task 6: Frontend crear / editar / ver + historial

**Files:**
- Create: `Frontend/src/app/views/ventas/programadas/programada-form.component.ts|html`
- Create: `Frontend/src/app/views/ventas/programadas/programada-detalle.component.ts|html` (ver + ejecuciones)
- Modify: routing (crear, editar/:id, ver/:id)
- Service HTTP: método en service existente de ventas o `venta-programada.service.ts` nuevo bajo `Frontend/src/app/services/`

**Form fields:** nombre, cliente (selector existente), documento, líneas (producto, cant, precio — cálculo totales simple como listados de compras/gastos si hay patrón liviano; no montar FacturacionComponent), frecuencia, día, hora, activo, emitir_automatico, enviar_email (disable si !emitir), max_ejecuciones.

**Detalle:** resumen + tabla ejecuciones (fecha, estado, modo, link `/venta/:id`, error).

- [ ] **Step 1: Form create/edit**
- [ ] **Step 2: Detalle + historial**
- [ ] **Step 3: Commit** `feat: form e historial facturas programadas`

---

### Task 7: Verificación end-to-end mínima

- [ ] Crear programada mensual; confirmar `proxima_ejecucion` y **0** ventas nuevas.
- [ ] Forzar `proxima_ejecucion` al pasado; correr `php artisan ventas:ejecutar-programadas`.
  - Con `emitir_automatico=false` → 1 venta borrador, 1 ejecución ok, count=1, sin email.
  - Segunda corrida mismo período → no duplica.
- [ ] `max_ejecuciones=1` → tras ok queda `activo=false`.
- [ ] Editar precio plantilla → venta ya generada no cambia.
- [ ] Confirmar recurrentes siguen iguales (`ventas:generar-recurrentes` no tocado en diff).
- [ ] Commit si hubo fixes: `fix: facturas programadas e2e`

---

## Spec coverage checklist

| Spec § | Task |
|--------|------|
| Tablas propias + FK ventas | 2 |
| Calendario / día 31 último día | 1 |
| CRUD + próxima al guardar | 3 |
| Cron 15 min + flujo emitir/borrador/email/error/idempotencia/max | 4 |
| UI listado mockup + tabs | 5 |
| Form + ver historial | 6 |
| No tocar recurrentes | 4, 7 |
| Precio vigencia fuera | N/A (no task) |
| Pruebas mínimas | 1, 7 |

## Placeholder / consistency self-review

- Nombres: `VentaProgramada`, `ProgramacionCalendario`, `EjecutarVentasProgramadasService`, comando `ventas:ejecutar-programadas`.
- Sin TBD; emisión FE remite a patrón recurrentes existente.
- Tabs: insertar **después** de Recurrentes en los 4 HTML de navegación ventas.
