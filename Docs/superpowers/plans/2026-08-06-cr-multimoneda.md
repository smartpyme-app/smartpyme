# Multimoneda Costa Rica (CRC/USD) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir emitir y registrar documentos CR en CRC o USD por documento, con TC BCCR (editable en ventas solo si la empresa lo habilita), montos nativos + equivalente CRC, y reportes fiscales en colones.

**Architecture:** Incremental sobre FE CR. Un servicio BCCR + tabla de caché; columnas de moneda en ventas/compras/gastos; mapper FE lee el documento; UI selector + preview; reportes usan `crc_equivalent_*`. Sin CurrencyEngine multi-país.

**Tech Stack:** Laravel (migrations, Eloquent, Artisan, PHPUnit), Angular FE CR, SOAP/HTTP BCCR, Jira SP-2078.

**Spec:** `Docs/superpowers/specs/2026-08-03-cr-multimoneda-design.md`

**Jira:** [SP-2078](https://smartpyme.atlassian.net/browse/SP-2078) + subtareas SP-2096 … SP-2102

## Global Constraints

- Solo mercado CR; no cambiar modelo SV/HN.
- Monedas Fase 1: `CRC` | `USD` únicamente.
- Montos `total`/`iva`/líneas = **nativos** en `currency_code`; CRC equivalent derivado.
- Default TC = BCCR indicador **318**; eliminar fallback **520** del path de emisión.
- Edición TC: solo ventas, pre-emisión, si `facturacion_fe.permitir_editar_tipo_cambio` = true (default false).
- Compras/gastos: sin edición TC en Fase 1.
- Post-emisión: moneda/TC inmutables.
- Multigiro: **fuera de alcance** (ticket aparte).
- No tocar `Frontend/src/environments/environment.ts` (cambio local del usuario).
- **NUNCA** usar `RefreshDatabase`, `migrate:fresh`, `migrate:refresh` ni ningún comando que dropee la BD de `.env`. Tests que necesiten tablas: SQLite `:memory:` creando solo lo necesario, o mocks sin DB (patrón CompraServiceTest / PlanillaServiceTest).
- Una commit por tarea en `feat/cr-multimoneda` (el usuario lo pidió explícitamente).

## File map

| File | Role |
|------|------|
| `Backend/config/services.php` | `bccr` env block |
| `Backend/database/migrations/*_create_bccr_tipos_cambio_table.php` | Caché TC |
| `Backend/app/Models/FacturacionElectronica/CostaRica/BccrTipoCambio.php` | Eloquent |
| `Backend/app/Services/FacturacionElectronica/CostaRica/BccrTipoCambioClient.php` | SOAP/HTTP BCCR |
| `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaTipoCambioService.php` | Resolver rate (rewrite) |
| `Backend/app/Console/Commands/CostaRica/SyncBccrTipoCambioCommand.php` | Job diario |
| `Backend/app/Console/Kernel.php` | Schedule |
| `Backend/tests/Unit/.../CostaRicaTipoCambioServiceTest.php` | Tests TC |
| `Backend/database/migrations/*_add_currency_fields_to_ventas_compras_gastos.php` | Columnas |
| `Backend/app/Models/Ventas/Venta.php` (+ Compra, Gasto, devoluciones) | fillable/casts |
| `Backend/app/Support/FacturacionElectronica/CostaRica/DocumentoMoneda.php` | Validar + CRC equiv |
| `Backend/app/Services/.../CostaRicaInvoiceFromVentaMapper.php` | Leer documento |
| Frontend facturación / empresa settings / gastos | Selector + flag UI |
| `CostaRicaXmlDocumentoParser` | Moneda XML |
| `ReporteDetalleIvaCrService` | CRC equivalent |

---

### Task 1: BCCR client + tabla + rewrite `CostaRicaTipoCambioService` (SP-2096)

**Files:**
- Create: `Backend/database/migrations/2026_08_06_100000_create_bccr_tipos_cambio_table.php`
- Create: `Backend/app/Models/FacturacionElectronica/CostaRica/BccrTipoCambio.php`
- Create: `Backend/app/Services/FacturacionElectronica/CostaRica/BccrTipoCambioClient.php`
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaTipoCambioService.php`
- Create: `Backend/app/Console/Commands/CostaRica/SyncBccrTipoCambioCommand.php`
- Modify: `Backend/app/Console/Kernel.php`
- Modify: `Backend/config/services.php`
- Create: `Backend/tests/Unit/Services/FacturacionElectronica/CostaRica/CostaRicaTipoCambioServiceTest.php`
- Create: `Backend/tests/Unit/Services/FacturacionElectronica/CostaRica/BccrTipoCambioClientTest.php`

**Interfaces:**
- Produces: `BccrTipoCambioClient::fetchVentaRate(\DateTimeInterface $date): ?float`
- Produces: `CostaRicaTipoCambioService::rateForDate(\DateTimeInterface $date): float` — throws domain exception si no hay rate
- Produces: `CostaRicaTipoCambioService::crcPorUsdVenta(Empresa $empresa, ?\DateTimeInterface $date = null): float` — deprecates manual/API/520; usa `rateForDate($date ?? today CR)`
- Consumes: `config('services.bccr.*')` — `email`, `token`, `name`, `url`

- [ ] **Step 1: Write failing unit tests for service (no BCCR HTTP)**

```php
<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Models\FacturacionElectronica\CostaRica\BccrTipoCambio;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * IMPORTANTE: NO usar RefreshDatabase. SQLite :memory: solo con bccr_tipos_cambio.
 */
final class CostaRicaTipoCambioServiceTest extends TestCase
{
    // setUp: config sqlite :memory: + Schema::create('bccr_tipos_cambio', ...) — ver archivo real.

    public function test_rate_for_date_reads_cached_row(): void
    {
        BccrTipoCambio::query()->create([
            'date' => '2026-08-05',
            'venta_reference_rate' => 512.34567,
            'fetched_at' => now(),
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldNotReceive('fetchVentaRate');

        $svc = new CostaRicaTipoCambioService($client);
        $this->assertSame(512.34567, $svc->rateForDate(new \DateTimeImmutable('2026-08-05')));
    }

    public function test_rate_for_date_fetches_and_caches_when_missing(): void
    {
        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')
            ->once()
            ->andReturn(510.12);

        $svc = new CostaRicaTipoCambioService($client);
        $rate = $svc->rateForDate(new \DateTimeImmutable('2026-08-04'));
        $this->assertSame(510.12, $rate);
        $row = BccrTipoCambio::query()->whereDate('date', '2026-08-04')->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(510.12, (float) $row->venta_reference_rate, 0.00001);
    }

    public function test_rate_for_date_throws_when_bccr_unavailable(): void
    {
        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')->once()->andReturn(null);

        $svc = new CostaRicaTipoCambioService($client);
        $this->expectException(\RuntimeException::class);
        $svc->rateForDate(new \DateTimeImmutable('2026-01-01'));
    }

    public function test_crc_por_usd_venta_does_not_use_fallback_520(): void
    {
        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')->andReturn(null);
        $svc = new CostaRicaTipoCambioService($client);
        $empresa = new \App\Models\Admin\Empresa();
        $this->expectException(\RuntimeException::class);
        $svc->crcPorUsdVenta($empresa, new \DateTimeImmutable('2026-01-01'));
    }
}
```

- [ ] **Step 2: Run tests → expect FAIL (classes/table missing)**

```bash
cd Backend && ./vendor/bin/phpunit tests/Unit/Services/FacturacionElectronica/CostaRica/CostaRicaTipoCambioServiceTest.php
```

Expected: FAIL (class/table not found)

- [ ] **Step 3: Migration + model**

```php
Schema::create('bccr_tipos_cambio', function (Blueprint $table) {
    $table->id();
    $table->date('date')->unique();
    $table->decimal('venta_reference_rate', 18, 5);
    $table->timestamp('fetched_at')->nullable();
    $table->timestamps();
});
```

Model `BccrTipoCambio`: `$table = 'bccr_tipos_cambio'`; fillable `date`, `venta_reference_rate`, `fetched_at`; casts `date` → date, rate → decimal:5.

- [ ] **Step 4: Config `services.bccr`**

```php
'bccr' => [
    'url' => env('BCCR_WS_URL', 'https://gee.bccr.fi.cr/Indicadores/Suscripciones/WS/wsindicadoreseconomicos.asmx'),
    'email' => env('BCCR_WS_EMAIL'),
    'token' => env('BCCR_WS_TOKEN'),
    'name' => env('BCCR_WS_NAME', 'SmartPyme'),
    'indicador_venta' => 318,
    'timeout_seconds' => (int) env('BCCR_WS_TIMEOUT', 25),
],
```

Documentar en `.env.example` (si existe en Backend) las tres vars. **Prerrequisito ops:** cuenta BCCR; sin token el client retorna null.

- [ ] **Step 5: Implement `BccrTipoCambioClient`**

`fetchVentaRate($date): ?float`:
1. Formatear fecha `d/m/Y`.
2. Preferir HTTP GET form-urlencoded a `{url}/ObtenerIndicadoresEconomicos` con query: `Indicador=318`, `FechaInicio`, `FechaFinal`, `Nombre`, `SubNiveles=N`, `CorreoElectronico`, `Token` (mismo contrato público BCCR).
3. Parsear XML/DataSet: buscar nodo `NUM_VALOR` / `num_valor` / valor numérico del indicador; si falla, intentar SOAP 1.1 POST con `SOAPAction: http://ws.sdde.bccr.fi.cr/ObtenerIndicadoresEconomicos`.
4. Si credenciales vacías o HTTP no OK o sin valor → `null` + Log::warning.
5. Unit test del parser con fixture XML string (método package-private o `parseResponse(string $body): ?float` public for test).

- [ ] **Step 6: Rewrite `CostaRicaTipoCambioService`**

```php
final class CostaRicaTipoCambioService
{
    public function __construct(private readonly BccrTipoCambioClient $client) {}

    public function rateForDate(\DateTimeInterface $date): float
    {
        $day = \Carbon\Carbon::instance(\DateTimeImmutable::createFromInterface($date))->startOfDay();
        $row = BccrTipoCambio::query()->whereDate('date', $day)->first();
        if ($row) {
            return (float) $row->venta_reference_rate;
        }
        $rate = $this->client->fetchVentaRate($day);
        if ($rate === null || $rate <= 0) {
            throw new \RuntimeException('No hay tipo de cambio BCCR (318) para la fecha '.$day->toDateString());
        }
        BccrTipoCambio::query()->updateOrCreate(
            ['date' => $day->toDateString()],
            ['venta_reference_rate' => $rate, 'fetched_at' => now()]
        );
        return (float) $rate;
    }

    public function crcPorUsdVenta(Empresa $empresa, ?\DateTimeInterface $date = null): float
    {
        // Ignorar tipo_cambio_usd_crc y APIs genéricas / 520.
        $date ??= now('America/Costa_Rica');
        return $this->rateForDate($date);
    }
}
```

Registrar binding si hace falta (Laravel auto-wires). Actualizar tests del mapper que construyen `new CostaRicaTipoCambioService()` sin args → pasar mock o `app()`.

- [ ] **Step 7: Artisan command + schedule**

Command signature: `bccr:sync-tipo-cambio {--date=}`  
- Default date = hoy `America/Costa_Rica`.  
- Llama `rateForDate`; exit 0 si ok, 1 si excepción.  

En `Kernel::schedule`:

```php
$schedule->command('bccr:sync-tipo-cambio')
    ->dailyAt('06:00')
    ->timezone('America/Costa_Rica')
    ->withoutOverlapping();
```

- [ ] **Step 8: Run unit tests → PASS**

```bash
cd Backend && ./vendor/bin/phpunit tests/Unit/Services/FacturacionElectronica/CostaRica/CostaRicaTipoCambioServiceTest.php tests/Unit/Services/FacturacionElectronica/CostaRica/BccrTipoCambioClientTest.php tests/Unit/Services/FacturacionElectronica/CostaRica/CostaRicaInvoiceFromVentaMapperTest.php
```

- [ ] **Step 9: Manual smoke (con env real)**

```bash
cd Backend && php artisan migrate --path=database/migrations/2026_08_06_100000_create_bccr_tipos_cambio_table.php
cd Backend && php artisan bccr:sync-tipo-cambio
```

Expected: fila en `bccr_tipos_cambio` o error claro si faltan credenciales (no 520).

- [ ] **Step 10: Commit (solo si el usuario lo pide)** — mensaje: `feat(cr): BCCR tipo de cambio 318 sin fallback 520`

---

### Task 2: Columnas moneda + `DocumentoMoneda` + backfill (SP-2101)

**Files:**
- Create: `Backend/database/migrations/2026_08_06_110000_add_currency_fields_to_ventas_compras_gastos.php`
- Modify: `Backend/app/Models/Ventas/Venta.php`
- Modify: `Backend/app/Models/Compras/Compra.php`
- Modify: `Backend/app/Models/Compras/Gastos/Gasto.php`
- Modify: modelo devoluciones FE CR usado en NC (si tiene totales propios; si no, hereda de venta)
- Create: `Backend/app/Support/FacturacionElectronica/CostaRica/DocumentoMoneda.php`
- Create: `Backend/tests/Unit/Support/FacturacionElectronica/CostaRica/DocumentoMonedaTest.php`
- Wire: controllers/services de create/update venta-compra-gasto CR (buscar puntos de `Venta::create` / store facturación)

**Interfaces:**
- Produces: `DocumentoMoneda::resolve(array $input, Empresa $empresa, \DateTimeInterface $fechaDoc): array`  
  Returns: `currency_code`, `exchange_rate`, `exchange_rate_date`, `crc_equivalent_total`, `crc_equivalent_iva`  
  Rules from spec §7.4 (flag editar solo ventas — Task 3; aquí aceptar param `$allowManualRate = false`).

- [ ] **Step 1: Failing tests `DocumentoMoneda`**

Casos: CRC → rate 1, equiv = nativos; USD sin rate en caché + allowManual false → exception; USD con rate BCCR mock → equiv = total * rate; rechazo rate ≤ 0 y rate == 1 para USD.

- [ ] **Step 2: Migration** en `ventas`, `compras`, `gastos` (+ devoluciones si aplica):

```php
$table->char('currency_code', 3)->default('CRC');
$table->decimal('exchange_rate', 18, 5)->default(1);
$table->date('exchange_rate_date')->nullable();
$table->decimal('crc_equivalent_total', 18, 5)->nullable();
$table->decimal('crc_equivalent_iva', 18, 5)->nullable();
```

Backfill en misma migration:

```php
DB::table('ventas')->whereNull('crc_equivalent_total')->update([
    // use query builder raw: currency_code CRC, exchange_rate 1,
    // crc_equivalent_total = total, crc_equivalent_iva = iva,
    // exchange_rate_date = DATE(fecha) or created_at
]);
```

Repetir para compras/gastos.

- [ ] **Step 3: fillable + casts** en modelos.

- [ ] **Step 4: Implement `DocumentoMoneda` + llamar al guardar** en paths CR de venta/compra/gasto (mínimo los que alimentan FE). Si el path no es CR, no setear (defaults CRC del backfill bastan).

- [ ] **Step 5: PHPUnit PASS + migrate local**

---

### Task 3: Flag `permitir_editar_tipo_cambio` (SP-2097)

**Files:**
- Backend: lectura/escritura via `Empresa::getCustomConfigValue` / set custom `facturacion_fe` (mismo patrón que otras keys FE)
- Frontend: settings empresa CR — checkbox “Permitir editar tipo de cambio en ventas”
- Modify: `DocumentoMoneda` — `$allowManualRate` true solo si venta + flag + documento no emitido

**Interfaces:**
- Config key: `facturacion_fe.permitir_editar_tipo_cambio` bool default false
- Si flag off: ignorar `exchange_rate` del request body en USD
- Si flag on (venta): aceptar body rate si `> 0 && !== 1`

- [ ] **Step 1: Test DocumentoMoneda con allowManual true/false**
- [ ] **Step 2: API/settings + UI checkbox**
- [ ] **Step 3: PHPUnit + smoke UI**

---

### Task 4: Mapper FE + pre-validación (SP-2100)

**Files:**
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaInvoiceFromVentaMapper.php` (todas las apariciones ~L56, L148, L1478, L1531)
- Modify: `CostaRicaCreditNoteFromDevolucionMapper.php` si aplica
- Modify: `CostaRicaFeEmitService.php` (pre-check)
- Modify: `CostaRicaInvoiceFromVentaMapperTest.php`

**Interfaces:**
- Mapper:  
  `currency_code` ← `$venta->currency_code ?? empresa`  
  `exchange_rate` ← `$venta->exchange_rate` (ya persistido); si falta y USD → resolver BCCR una vez y preferible fallar pidiendo re-guardar

- [ ] **Step 1: Update mapper tests** — venta con `currency_code=USD`, `exchange_rate=512.5` → payload currency matches; no llama APIs genéricas.
- [ ] **Step 2: Replace empresa.moneda-only logic** en mapper.
- [ ] **Step 3: Pre-validación emit:** CRC ⇒ rate==1; USD ⇒ rate>0 && rate!=1; else 422 mensaje claro.
- [ ] **Step 4: Sandbox Hacienda** (manual checklist en subtarea Jira).

---

### Task 5: UI ventas (SP-2102)

**Files:** (localizar componentes facturación CR v2 — buscar `facturacion` + pipe moneda empresa)

- Selector CRC|USD (default `empresa.moneda`)
- Endpoint o campo en payload venta: `currency_code`, opcional `exchange_rate` si flag
- Preview: TC, fecha, `total * rate` CRC
- Detalle venta: mostrar nativo + TC + conversión
- Post-emitido: disable selector/TC

- [ ] **Step 1: API GET rate** — reutilizar o añadir `GET` que llame `CostaRicaTipoCambioService::rateForDate` (o el proxy existente solo para UI preview; persistencia sigue siendo BCCR service)
- [ ] **Step 2: UI create/edit venta**
- [ ] **Step 3: UI detalle**
- [ ] **Step 4: Smoke manual CRC + USD**

---

### Task 6: Compras/gastos + XML import (SP-2099)

**Files:**
- UI compra/gasto CR: selector + preview (TC no editable)
- Modify: `CostaRicaXmlDocumentoParser` (o equivalente) — leer `CodigoMoneda`, `TipoCambio`
- Al importar: set currency; prefer BCCR para `exchange_rate` + CRC equiv; log si XML difiere

- [ ] **Step 1: Unit test parser** con fixture XML USD
- [ ] **Step 2: Parser + import path**
- [ ] **Step 3: UI compra/gasto**
- [ ] **Step 4: Smoke import**

---

### Task 7: Reportes IVA / dash CRC equivalent (SP-2098)

**Files:**
- Modify: `Backend/app/Services/Contabilidad/CostaRica/ReporteDetalleIvaCrService.php` — dejar de hardcodear CRC/1; usar campos documento
- Grep CR: sumas de `venta.total` / `iva` en dashboards → `crc_equivalent_*` con fallback `currency_code=CRC ? nativo : null-safe`
- Export: `ReporteDetalleIvaVentasExport.php`

- [ ] **Step 1: Test o assertion** que fila USD use crc_equivalent
- [ ] **Step 2: Patch servicios**
- [ ] **Step 3: Smoke reporte con venta USD de prueba**

---

## Spec coverage checklist

| Spec § | Task |
|--------|------|
| BCCR + eliminar 520 | 1 |
| Columnas + nativos + backfill | 2 |
| Flag editar TC ventas | 3 |
| Mapper FE / XML emit | 4 |
| UI ventas | 5 |
| Compras/gastos + XML ingest | 6 |
| Reportes CRC equiv | 7 |
| Multigiro | — (aparte) |

## Execution notes

- Orden estricto: 1 → 2 → (3∥4) → 5; 6 después de 2; 7 después de 2.
- Credenciales BCCR bloquean smoke real de Task 1/4; tests unitarios con mock no las requieren.
- Branch sugerida: `feat/cr-multimoneda` desde `bk-main` (o worktree aislado).
