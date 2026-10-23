# Facturación electrónica CR — Anexos 4.4 al 1 de noviembre 2026

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar la emisión de comprobantes electrónicos de Costa Rica alineada con la actualización de Anexos y Estructuras 4.4 (comunicado DGT del 22 de abril de 2026) antes del 1 de noviembre de 2026.

**Architecture:** Un catálogo único decide el código de referencia y el tipo de documento. Las notas de crédito y débito, la reemisión y el recibo de pago lo consumen. La cédula jurídica y la clave se tratan como texto. No se cambia el flujo de El Salvador ni el de Honduras.

**Tech Stack:** Laravel (PHPUnit), Angular, paquete `dazza-dev/dgt-cr` + `dazza-dev/dgt-xml-generator` (parches en `Backend/patches/`).

**Fuentes:** [comunicado DGT](https://www.hacienda.go.cr/docs/ActualizacionAnexosyEstructurasVersion4.4CEv2.pdf), [Anexos y Estructuras 4.4](https://www.hacienda.go.cr/docs/ANEXOS_Y_ESTRUCTURAS_V4.4.pdf) (bitácora 22/04/2026, Nota 9 y Nota 10).

## Global Constraints

- Sigue siendo versión **4.4**. No hay versión 4.5.
- Zona horaria del período: `America/Costa_Rica`. El período es el mes calendario (`Y-m`).
- Devolución de mercancía: código **06** en cualquier período. El efecto queda en el período de la nota.
- Mismo período, error o corrección: **01** anula, **02** corrige monto, **07** sustituye el comprobante nuevo.
- Período anterior, error material: **13** anula, **14** corrige monto, **15** sustituye el comprobante nuevo. El efecto queda en el período del comprobante original.
- Comprobante rechazado por Hacienda: tipo de documento **10** + código **16**. No se anula con nota de crédito.
- Código **12** solo si la nota es por exoneración de impuesto local aprobada después de facturar.
- Código **17** solo dentro del Recibo Electrónico de Pago.
- Tipos de documento nuevos: **19** factura electrónica de exportación, **20** recibo electrónico de pago. En 17, 18, 19 y 20 la clave referenciada es obligatoria.
- En el recibo de pago solo se referencian los tipos **01, 04, 07, 08, 10 y 20**.
- Cédula jurídica: 10 caracteres alfanuméricos, sin guiones. Cédula física sigue en 9 dígitos. DIMEX 11 o 12 dígitos. NITE 10 dígitos.
- Clave: texto de 50 caracteres. Puede incluir letras.
- No tocar `Frontend/src/environments/environment.ts`.
- No commitear salvo que se pida.
- Tests con PHPUnit. No usar `RefreshDatabase`, `migrate:fresh` ni `migrate:refresh`.

---

## Resumen de lo que pide Hacienda

Uso voluntario desde el 22 de abril de 2026 (STAG y producción). Uso obligatorio el **1 de noviembre de 2026**.

1. Cédula jurídica alfanumérica (10 caracteres) en emisor, receptor y terceros. La clave de 50 posiciones puede llevar letras.
2. Códigos de referencia de la Nota 9: ajuste del **12**; alta de **13, 14, 15, 16 y 17**, con el período contable indicado arriba.
3. Tipos de documento de la Nota 10: **19** y **20**.
4. Teléfono: mínimo 8 dígitos, excepto números cortos (ejemplo 911).
5. IVA devuelto: obligatorio solo en servicios de salud pagados con tarjeta (medio de pago **02**). Si el pago es mixto o hay otras líneas: `(monto pagado con tarjeta / venta neta) × IVA de las líneas de salud al 4%`. Ese monto no puede superar el IVA de esas líneas.

## Lo que ya cumple

- [x] Emisión 4.4 de factura 01, tiquete 04, nota de crédito 03, nota de débito 02 y factura de compra 08.
- [x] Devolución de mercancía con código **06** (`CostaRicaCreditNoteFromDevolucionMapper`, `referenced_code`).
- [x] Teléfono corto: `telefonoCr()` envía los dígitos que hay y no rellena a 8. El 911 sale como `911`.

Esos tres no se reabren. El resto de este archivo sí.

## Orden

Hacer las tareas en este orden. Cada una deja el sistema usable si la siguiente aún no está.

1. Catálogo de códigos y regla de período.
2. Nota de crédito y nota de débito usan ese catálogo.
3. Sustitución: código 15 (después de un 13) y código 16 (rechazado).
4. Cédula jurídica alfanumérica y clave como texto.
5. Factura de exportación y tipo de documento 19.
6. Recibo electrónico de pago, código 17 y tipo 20.
7. IVA devuelto de servicios de salud con tarjeta.
8. Prueba en STAG de los casos que el negocio sí emite.

## Mapa de archivos

| Archivo | Rol |
|---|---|
| `Backend/app/Support/FacturacionElectronica/CostaRica/ReferenciaComprobanteCr.php` | Catálogo Nota 9 / Nota 10 y regla de período. Lo crean la tarea 1 y lo usan las demás. |
| `Backend/tests/Unit/Support/FacturacionElectronica/CostaRica/ReferenciaComprobanteCrTest.php` | Pruebas de esa regla. |
| `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaCreditNoteFromDevolucionMapper.php` | Hoy fija `referenced_code` = `06`. |
| `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaFeEmitService.php` | Nota de débito fija `02`. Exportación sale como factura `invoice` / tipo `01`. |
| `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaInvoiceFromVentaMapper.php` | `soloDigitos()`, `esClaveComprobanteElectronicoCr()` exige 50 dígitos, pagos siempre medio `01`. |
| `Backend/app/Support/FacturacionElectronica/TipoIdentificacionReceptor.php` | Tipo `02` exige 10 dígitos. |
| `Backend/app/Http/Controllers/Api/Admin/CostaRicaFeCatalogController.php` | Consulta de contribuyente borra las letras. |
| `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaHaciendaPublicApiService.php` | Igual, `preg_replace('/\D/')`. |
| `Frontend/src/app/utils/identificacion-tipos.util.ts` | Máscara jurídica CR `0-0000-0000`. |
| `Backend/app/Models/Ventas/Abono.php` | Abono de venta a crédito. Punto de enganche del recibo de pago. |
| `Backend/patches/` | Parche del generador XML si el XSD instalado no acepta los códigos nuevos o la clave con letras. |

---

### Task 1: Catálogo Nota 9, Nota 10 y período

**Files:**
- Create: `Backend/app/Support/FacturacionElectronica/CostaRica/ReferenciaComprobanteCr.php`
- Test: `Backend/tests/Unit/Support/FacturacionElectronica/CostaRica/ReferenciaComprobanteCrTest.php`

**Interfaces:**
- Produces: `ReferenciaComprobanteCr::codigoNota(string $motivo, \DateTimeInterface $fechaOriginal, \DateTimeInterface $fechaNota): string`
- Produces: `ReferenciaComprobanteCr::codigoSustitucion(string $codigoNotaQueAnulo): string` — `01` → `07`, `13` → `15`
- Produces: `ReferenciaComprobanteCr::esPeriodoAnterior(\DateTimeInterface $fechaOriginal, \DateTimeInterface $fechaNota): bool`
- Produces: constantes `MOTIVO_DEVOLUCION`, `MOTIVO_ANULA`, `MOTIVO_CORRIGE_MONTO`, `MOTIVO_EXONERACION_POSTERIOR`, `CODIGO_RECHAZADO = '16'`, `CODIGO_PAGO_REP = '17'`, `TIPO_RECHAZADO = '10'`, `TIPO_EXPORTACION = '19'`, `TIPO_RECIBO_PAGO = '20'`
- Produces: `ReferenciaComprobanteCr::tiposPermitidosEnReciboPago(): array` — `['01','04','07','08','10','20']`

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Unit\Support\FacturacionElectronica\CostaRica;

use App\Support\FacturacionElectronica\CostaRica\ReferenciaComprobanteCr;
use PHPUnit\Framework\TestCase;

final class ReferenciaComprobanteCrTest extends TestCase
{
    public function test_devolucion_es_06_aunque_la_factura_sea_de_otro_mes(): void
    {
        $codigo = ReferenciaComprobanteCr::codigoNota(
            ReferenciaComprobanteCr::MOTIVO_DEVOLUCION,
            new \DateTimeImmutable('2026-08-15 10:00:00', new \DateTimeZone('America/Costa_Rica')),
            new \DateTimeImmutable('2026-09-02 10:00:00', new \DateTimeZone('America/Costa_Rica')),
        );

        self::assertSame('06', $codigo);
    }

    public function test_anula_y_corrige_del_mismo_mes_siguen_en_01_y_02(): void
    {
        $agosto = new \DateTimeImmutable('2026-08-10 10:00:00', new \DateTimeZone('America/Costa_Rica'));
        $mismo = new \DateTimeImmutable('2026-08-28 10:00:00', new \DateTimeZone('America/Costa_Rica'));

        self::assertSame('01', ReferenciaComprobanteCr::codigoNota(ReferenciaComprobanteCr::MOTIVO_ANULA, $agosto, $mismo));
        self::assertSame('02', ReferenciaComprobanteCr::codigoNota(ReferenciaComprobanteCr::MOTIVO_CORRIGE_MONTO, $agosto, $mismo));
        self::assertSame('07', ReferenciaComprobanteCr::codigoSustitucion('01'));
    }

    public function test_error_material_de_otro_mes_usa_13_14_y_15(): void
    {
        $agosto = new \DateTimeImmutable('2026-08-31 22:00:00', new \DateTimeZone('America/Costa_Rica'));
        $setiembre = new \DateTimeImmutable('2026-09-01 08:00:00', new \DateTimeZone('America/Costa_Rica'));

        self::assertTrue(ReferenciaComprobanteCr::esPeriodoAnterior($agosto, $setiembre));
        self::assertSame('13', ReferenciaComprobanteCr::codigoNota(ReferenciaComprobanteCr::MOTIVO_ANULA, $agosto, $setiembre));
        self::assertSame('14', ReferenciaComprobanteCr::codigoNota(ReferenciaComprobanteCr::MOTIVO_CORRIGE_MONTO, $agosto, $setiembre));
        self::assertSame('15', ReferenciaComprobanteCr::codigoSustitucion('13'));
    }

    public function test_exoneracion_posterior_es_12_sin_mirar_el_mes(): void
    {
        $codigo = ReferenciaComprobanteCr::codigoNota(
            ReferenciaComprobanteCr::MOTIVO_EXONERACION_POSTERIOR,
            new \DateTimeImmutable('2026-01-01', new \DateTimeZone('America/Costa_Rica')),
            new \DateTimeImmutable('2026-11-02', new \DateTimeZone('America/Costa_Rica')),
        );

        self::assertSame('12', $codigo);
    }

    public function test_recibo_de_pago_no_acepta_notas(): void
    {
        self::assertSame(['01', '04', '07', '08', '10', '20'], ReferenciaComprobanteCr::tiposPermitidosEnReciboPago());
        self::assertNotContains('02', ReferenciaComprobanteCr::tiposPermitidosEnReciboPago());
        self::assertNotContains('03', ReferenciaComprobanteCr::tiposPermitidosEnReciboPago());
    }
}
```

- [ ] **Step 2: Correr el test y ver que falla**

Run desde `Backend/`:

```bash
php vendor/bin/phpunit tests/Unit/Support/FacturacionElectronica/CostaRica/ReferenciaComprobanteCrTest.php
```

Expected: FAIL, clase `ReferenciaComprobanteCr` no existe.

- [ ] **Step 3: Implementar el catálogo**

`codigoNota` compara `Y-m` en `America/Costa_Rica`.

| Motivo | Mismo mes | Mes anterior |
|---|---|---|
| `devolucion` | 06 | 06 |
| `anula` | 01 | 13 |
| `corrige_monto` | 02 | 14 |
| `exoneracion_posterior` | 12 | 12 |

`codigoSustitucion`: `01` devuelve `07`, `13` devuelve `15`. Cualquier otro código lanza `InvalidArgumentException`.

Motivo desconocido lanza `InvalidArgumentException`. No hay default silencioso a `02` ni a `06`.

- [ ] **Step 4: Correr el test y ver que pasa**

El mismo comando de phpunit. Expected: OK, 5 tests.

---

### Task 2: Nota de crédito y nota de débito usan el catálogo

**Files:**
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaCreditNoteFromDevolucionMapper.php` (hoy línea del `referenced_code` => `06`)
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaFeEmitService.php` método `emitirNotaDebitoDesdeVenta` (hoy `referenced_code` => `02`)
- Modify: request de nota de débito `EmitirFeCrNotaDebitoRequest` y el controller `CostaRicaFeController::emitirNotaDebito`
- Modify: `Frontend/src/app/views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component.ts` y su HTML, solo cuando `esFeCostaRica()`
- Test: `Backend/tests/Unit/Services/FacturacionElectronica/CostaRica/CostaRicaCreditNoteReceptorAlineadoTest.php` no cubre el código; crear `CostaRicaCodigoReferenciaNotaTest.php` que instancie el catálogo contra fechas de una venta, sin emitir a Hacienda.

**Interfaces:**
- Consumes: `ReferenciaComprobanteCr::codigoNota`
- Produces: la NC envía `referenced_code` según motivo. Si el motivo no viene, sigue `devolucion` (06), que es el comportamiento actual.
- Produces: la ND envía `02` o `14` según el mes de la factura original. Motivo fijo `corrige_monto`.

- [ ] **Step 1: Test de la nota de débito**

Caso A: factura y nota en agosto 2026 → el arreglo `referenced_documents[0].referenced_code` es `02`.

Caso B: factura el 31 de agosto 2026 y nota el 1 de setiembre 2026, ambas en `America/Costa_Rica` → el código es `14`.

Extraer la decisión a un método puro, por ejemplo `CostaRicaFeEmitService::codigoReferenciaNotaDebito(\DateTimeInterface $fechaFactura, \DateTimeInterface $fechaNota): string`, que delega en el catálogo. El test llama ese método. No llama a `sendDocument()`.

- [ ] **Step 2: Correr el test y ver que falla**

```bash
php vendor/bin/phpunit tests/Unit/Services/FacturacionElectronica/CostaRica/CostaRicaCodigoReferenciaNotaTest.php
```

- [ ] **Step 3: Conectar NC y ND**

En `CostaRicaCreditNoteFromDevolucionMapper::buildDocumentData`, reemplazar el `'06'` fijo:

```php
$motivo = (string) ($devolucion->motivo_fe_cr ?: ReferenciaComprobanteCr::MOTIVO_DEVOLUCION);
$referenced[0]['referenced_code'] = ReferenciaComprobanteCr::codigoNota(
    $motivo,
    Carbon::parse($facturaOriginal->fecha)->timezone('America/Costa_Rica'),
    Carbon::now('America/Costa_Rica'),
);
```

Guardar `motivo_fe_cr` en la devolución. Valores permitidos: `devolucion`, `anula`, `exoneracion_posterior`. Si la columna no existe, agregar una migración `string` nullable de 40 caracteres. Default en UI: `devolucion`.

En la pantalla de devolución CR, un selector con tres textos:

- Devolución de mercancía
- Anula el documento por error
- Exoneración de impuesto local posterior a la factura

No pedir al usuario el número 06/01/13. El catálogo lo calcula con la fecha de la factura.

En `emitirNotaDebitoDesdeVenta`, cambiar el `'02'` fijo por `codigoReferenciaNotaDebito` con la fecha de la venta y `Carbon::now('America/Costa_Rica')`.

- [ ] **Step 4: Correr el test**

Expected: PASS. Además correr `CostaRicaCreditNoteReceptorAlineadoTest` para confirmar que el receptor de la NC no se movió.

---

### Task 3: Código 15 al sustituir y código 16 al reemitir un rechazado

**Files:**
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaInvoiceFromVentaMapper.php` (armado de `referenced_documents` de la factura de venta; hoy la referencia de compra usa código `04`, no tocar ese camino)
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaFeEmitService.php` en el método que emite la venta (`enviarYPersistirVenta` y el punto que arma `$data` antes de emitir)
- Modify: la pantalla de venta CR que reintenta una emisión rechazada

**Interfaces:**
- Consumes: `ReferenciaComprobanteCr::codigoSustitucion`, `TIPO_RECHAZADO`, `CODIGO_RECHAZADO`
- Produces: si la venta nueva reemplaza una anulada con nota código `01`, la factura lleva referencia código `07`. Si la nota fue `13`, lleva `15`.
- Produces: si se reemite un comprobante con estado Hacienda rechazado, la referencia es tipo de documento `10`, número = clave rechazada, código `16`.

- [ ] **Step 1: Test puro de los dos casos**

```php
public function test_despues_de_anular_con_13_el_comprobante_nuevo_usa_15(): void
{
    self::assertSame('15', ReferenciaComprobanteCr::codigoSustitucion('13'));
    self::assertSame('07', ReferenciaComprobanteCr::codigoSustitucion('01'));
}

public function test_rechazado_usa_tipo_10_y_codigo_16(): void
{
    $ref = ReferenciaComprobanteCr::referenciaDeRechazado('506310126...');
    self::assertSame('10', $ref['document_type']);
    self::assertSame('16', $ref['referenced_code']);
    self::assertSame('506310126...', $ref['document_number']);
}
```

`referenciaDeRechazado` rechaza una clave vacía.

- [ ] **Step 2: Correr el test y ver que falla** en el método nuevo.

- [ ] **Step 3: Al emitir una factura, agregar `referenced_documents` solo en estos dos casos**

- La venta guarda `clave_sustituida` (50 caracteres) y `codigo_nota_anulacion` (`01` o `13`). Si ambos vienen, una sola referencia con `document_type` `01`, `document_number` = clave, `referenced_code` = `codigoSustitucion`.
- Si la venta previa está rechazada y el usuario emite otra en su lugar, una sola referencia con `referenciaDeRechazado(claveRechazada)`.
- Si no hay nada que sustituir, la factura de venta sigue sin nodo de referencia, como hoy.
- No usar nota de crédito para un XML que Hacienda rechazó.

- [ ] **Step 4: Correr el test.** Expected: PASS.

En la UI de una venta CR rechazada, el botón de reemitir debe crear el documento nuevo con esa referencia, no reenviar el mismo XML.

---

### Task 4: Cédula jurídica alfanumérica y clave como texto

**Files:**
- Modify: `Backend/app/Support/FacturacionElectronica/TipoIdentificacionReceptor.php` (`estructuraValidaCostaRicaFactura`, `numeroCostaRica`)
- Modify: `Backend/tests/Unit/Support/FacturacionElectronica/TipoIdentificacionReceptorCrFacturaTest.php`
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaInvoiceFromVentaMapper.php` (`soloDigitos` en la identificación tipo `02`, `esClaveComprobanteElectronicoCr` cerca de la línea 1836)
- Modify: `Backend/app/Http/Controllers/Api/Admin/CostaRicaFeCatalogController.php` método `contribuyente`
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaHaciendaPublicApiService.php` método `contribuyente`
- Modify: `Frontend/src/app/utils/identificacion-tipos.util.ts` y `identificacion-tipos.util.spec.ts`
- Modify: el input de identificación del cliente y del emisor CR para que el tipo `02` acepte letras

**Interfaces:**
- Produces: `TipoIdentificacionReceptor::normalizarNumeroCostaRica(string $tipo, string $raw): string`
- Tipo `02`: quitar guiones y espacios, conservar letras, exigir largo 10. Ejemplo de pantalla `3-101-A00001` → valor emitido `3101A00001`.
- Tipos `01`, `03` y `04`: siguen solo dígitos, con los largos actuales (9, 11–12 y 10).
- Clave válida: 50 caracteres `[0-9A-Za-z]`. Deja de exigir 50 dígitos.

- [ ] **Step 1: Ampliar el test existente**

En `TipoIdentificacionReceptorCrFacturaTest`:

```php
public function test_cedula_juridica_acepta_letras_de_10_caracteres(): void
{
    self::assertTrue(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('02', '3101A00001'));
    self::assertSame('3101A00001', TipoIdentificacionReceptor::normalizarNumeroCostaRica('02', '3-101-A00001'));
    self::assertFalse(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('02', '3101A0000'));
}

public function test_cedula_fisica_sigue_siendo_9_digitos(): void
{
    self::assertTrue(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('01', '123456789'));
    self::assertFalse(TipoIdentificacionReceptor::estructuraValidaCostaRicaFactura('01', '12345678A'));
}
```

- [ ] **Step 2: Correr el test y ver que falla**

```bash
php vendor/bin/phpunit tests/Unit/Support/FacturacionElectronica/TipoIdentificacionReceptorCrFacturaTest.php
```

- [ ] **Step 3: Dejar de borrar las letras solo en tipo 02**

`estructuraValidaCostaRicaFactura` hoy hace `preg_replace('/\D/')` sobre todo. Separar: tipo `02` usa `preg_replace('/[^0-9A-Za-z]/', '')` y `strlen === 10`. El resto no cambia.

En el mapper, `emisorDatos` y `receptorDatosVenta` deben usar `normalizarNumeroCostaRica` cuando el tipo es `02`, y `soloDigitos` en los demás tipos.

`esClaveComprobanteElectronicoCr`:

```php
$clave = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $s) ?? '');
return strlen($clave) === 50;
```

Al copiar la clave a `document_number`, no aplicar `preg_replace('/\D/')`.

Consulta de contribuyente: si el tipo es jurídico, enviar a Hacienda los 10 caracteres alfanuméricos. La API pública puede seguir rechazando letras hasta que el Registro Nacional las asigne; en ese caso devolver el mismo JSON vacío de “sin información” que ya se usa en 400/404, sin un 422 por “debe ser dígitos”.

Frontend: para Costa Rica y código `02`, quitar la máscara `0-0000-0000` (esa máscara bloquea letras). Aceptar hasta 12 caracteres de captura contando guiones, y normalizar a 10 al guardar. Actualizar el spec: `mascaraIdentificacion('02', cr)` queda `''`.

Revisar el XSD de `dgt-xml-generator`. Si `Numero` o `Clave` siguen con patrón solo numérico, agregar un parche en `Backend/patches/` igual que los parches `dgt-xml-generator-*.patch` ya existentes, y registrarlo en `composer.json`.

- [ ] **Step 4: Correr el test de identificación y el spec de Angular**

```bash
php vendor/bin/phpunit tests/Unit/Support/FacturacionElectronica/TipoIdentificacionReceptorCrFacturaTest.php
```

```bash
npx ng test --include=src/app/utils/identificacion-tipos.util.spec.ts --browsers=ChromeHeadless --watch=false
```

Expected: PASS. Una cédula física con letra sigue rechazada.

---

### Task 5: Factura electrónica de exportación y tipo 19

**Files:**
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaFeEmitService.php` (`esDocumentoFacturaCr` trata “exportación” como factura 01; `enviarYPersistirVenta` usa `setDocumentType('invoice')` y tipo DTE `01`)
- Modify: `CostaRicaInvoiceFromVentaMapper` para el consecutivo y los nodos que el XSD de FEE exige (partida arancelaria de 12 dígitos cuando el CABYS de bien empieza en 0–4)
- Test: método puro `tipoDocumentoExportacion(string $nombreDocumento): ?string`

**Interfaces:**
- Consumes: `ReferenciaComprobanteCr::TIPO_EXPORTACION`
- Produces: un documento cuyo nombre contiene “exportación” se emite como factura electrónica de exportación, no como factura 01.
- Produces: una NC o ND sobre una FEE referencia `document_type` `19` y la clave de esa FEE.

- [ ] **Step 1: Test**

Nombre `Factura de exportación` → tipo de comprobante exportación.

Nombre `Factura` → sigue siendo factura 01.

Referencia a una FEE → `document_type` `19` y `document_number` de 50 caracteres. Si falta la clave, excepción, no tipo `01`.

- [ ] **Step 2: Correr el test y ver que falla.**

- [ ] **Step 3: Emitir FEE con el tipo que exponga el generador XML**

Confirmar en el paquete instalado el `setDocumentType` de exportación (el XSD oficial es `facturaElectronicaExportacion`). Si el paquete no lo tiene, parche en `Backend/patches/` antes de cambiar el emisor.

`esDocumentoFacturaCr` debe devolver false para exportación. Un método nuevo `esDocumentoExportacionCr` cubre `exportación` / `exportacion`.

El secuencial de FEE usa el tipo de comprobante de exportación del consecutivo de 20 posiciones (no el `01` de factura). Guardar `tipo_dte` con ese código.

En la NC, si `facturaOriginal->tipo_dte` es el de exportación, `document_type` de la referencia es `19`. En la ND, igual.

Partida arancelaria: si la línea es un bien (CABYS empieza en 0, 1, 2, 3 o 4) y el documento es FEE, el campo es obligatorio a 12 dígitos. Si el producto no lo tiene, fallar antes de firmar con un mensaje que nombre el producto.

- [ ] **Step 4: Correr el test.** Expected: PASS.

Una factura nacional no gana partida arancelaria ni tipo 19.

---

### Task 6: Recibo electrónico de pago, código 17 y tipo 20

**Files:**
- Create: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaReciboPagoMapper.php`
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaFeEmitService.php`
- Modify: `Backend/app/Http/Controllers/Api/Ventas/AbonosController.php` (o `AbonoVentaService`) para disparar la emisión al guardar un abono de una venta CR a crédito que ya tiene clave
- Modify: el XSD/generador si no existe el tipo `reciboElectronicoPago`

**Interfaces:**
- Consumes: `ReferenciaComprobanteCr::CODIGO_PAGO_REP`, `TIPO_RECIBO_PAGO`, `tiposPermitidosEnReciboPago()`
- Produces: `CostaRicaReciboPagoMapper::build(Abono $abono, Venta $venta): array` con una referencia código `17`
- Produces: `CostaRicaFeEmitService::emitirReciboPago(Abono $abono): array`

- [ ] **Step 1: Test del mapper sin HTTP**

Venta con `tipo_dte` `01` y clave de 50 caracteres, abono de 10000 → la referencia queda tipo `01`, código `17`, número = clave de la venta.

Venta con `tipo_dte` `03` (nota de crédito) → el mapper lanza `InvalidArgumentException` porque el recibo no puede referenciar una nota.

Venta sin clave → excepción, no se arma XML.

- [ ] **Step 2: Correr el test y ver que falla.**

- [ ] **Step 3: Emitir el recibo solo al abonar una venta a crédito ya aceptada**

Condiciones para emitir, todas juntas:

- Empresa con facturación electrónica de Costa Rica.
- La venta está a crédito y tiene clave aceptada.
- El `tipo_dte` de la venta está en `tiposPermitidosEnReciboPago()`.
- El abono todavía no tiene clave.

La referencia lleva código `17`, tipo = `tipo_dte` de la venta, número = clave, fecha = fecha de la venta. El monto del recibo es el del abono, no el total de la factura.

Medio de pago del recibo = el del abono (efectivo `01`, tarjeta `02`, transferencia `04`). Dejar de heredar el `01` fijo de `pagosDesdeMonto` para este documento.

Si el generador no conoce el comprobante, parche XSD `reciboElectronicoPago` antes de conectar el abono. Hasta que ese parche exista, el abono se guarda igual que hoy y no se intenta firmar un XML inválido.

- [ ] **Step 4: Correr el test.** Expected: PASS.

Un abono a una factura de contado no emite recibo.

---

### Task 7: IVA devuelto en servicios de salud pagados con tarjeta

**Files:**
- Create: `Backend/app/Support/FacturacionElectronica/CostaRica/IvaDevueltoSaludCr.php`
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaInvoiceFromVentaMapper.php` (`pagosDesdeMonto` hoy fuerza medio `01`; el resumen de la factura no envía `total_iva_devuelto`)
- Test: `Backend/tests/Unit/Support/FacturacionElectronica/CostaRica/IvaDevueltoSaludCrTest.php`

**Interfaces:**
- Produces: `IvaDevueltoSaludCr::monto(float $ventaNeta, float $montoTarjeta, float $ivaLineasSalud4): float`
- La línea cuenta como salud solo si su CABYS está en `Backend/config/fe-cr-cabys-salud.php` (lista de códigos de 13 dígitos) y la tarifa de IVA de esa línea es 4%.
- Medio de pago tarjeta = `02` (Nota 6 del anexo).

- [ ] **Step 1: Test de la fórmula del anexo**

```php
public function test_pago_mixto_prorratea_y_no_supera_el_iva_de_salud(): void
{
    // venta neta 100000, tarjeta 25000, IVA salud 4% = 4000
    // (25000 / 100000) * 4000 = 1000
    self::assertSame(1000.0, IvaDevueltoSaludCr::monto(100000, 25000, 4000));
}

public function test_el_devuelto_no_puede_superar_el_iva_de_las_lineas_de_salud(): void
{
    self::assertSame(4000.0, IvaDevueltoSaludCr::monto(100000, 100000, 4000));
    self::assertSame(0.0, IvaDevueltoSaludCr::monto(100000, 0, 4000));
    self::assertSame(0.0, IvaDevueltoSaludCr::monto(0, 10000, 4000));
}
```

El tercer caso del tope: si el prorrateo diera más que `ivaLineasSalud4`, devolver `ivaLineasSalud4`.

- [ ] **Step 2: Correr el test y ver que falla.**

- [ ] **Step 3: Enviar el nodo solo cuando aplica**

Mapear el medio de pago real de la venta. Tarjeta → `02`. El resto de medios según la Nota 6, sin dejar todo en `01`.

Sumar el IVA de las líneas cuyo CABYS está en `config('fe-cr-cabys-salud')` y cuya tarifa es 4. Si esa suma es 0 o el monto con tarjeta es 0, no enviar `TotalIVADevuelto`.

Si aplica, el resumen lleva `total_iva_devuelto` con el resultado de `monto()`, y `TotalComprobante` resta ese valor, como ya describe el anexo.

Llenar `fe-cr-cabys-salud.php` con los CABYS de servicios médicos que Hacienda usa en esa validación. Sacarlos del catálogo CABYS oficial (búsqueda “servicios de salud” / “servicios médicos”), no inventar prefijos. Si la lista queda vacía, el nodo no se envía: una factura sin esas líneas no debe llevar el campo.

- [ ] **Step 4: Correr el test.** Expected: PASS.

Una venta en efectivo, o sin líneas de esa lista, no incluye el campo.

---

### Task 8: Prueba en STAG de lo que el negocio emite

No es código de más. Es el cierre antes del 1 de noviembre. Ambiente de pruebas de Hacienda (STAG), con el emisor de prueba ya configurado en la empresa.

- [ ] Factura 01 del mes en curso. Debe aceptarse igual que hoy.
- [ ] Devolución de mercancía de una factura de otro mes. El XML debe llevar código **06**, no 13.
- [ ] Nota de débito por corrección de monto de una factura del mes en curso. Código **02**.
- [ ] Nota de débito por corrección de monto de una factura del mes anterior. Código **14**.
- [ ] Anulación por error de una factura del mes anterior (motivo “anula”, no “devolución”). Código **13**. El comprobante nuevo que la sustituye lleva código **15**.
- [ ] Reemitir un comprobante rechazado. Tipo de documento **10** y código **16**. No debe salir una nota de crédito.
- [ ] Si se emite exportación: el comprobante es FEE y una nota sobre ella referencia tipo **19** con clave de 50 caracteres.
- [ ] Si se abona una factura a crédito aceptada: sale recibo de pago con código **17**. Un abono no intenta referenciar una nota de crédito.
- [ ] Cliente con cédula `3101A00001` (tipo 02). El XML lleva esas letras. Una cédula física sigue en 9 dígitos.
- [ ] Teléfono 911 en el receptor, si se captura. El nodo lleva `911`, no ocho ceros.
- [ ] Solo si hay un producto de la lista de salud pagado con tarjeta: el XML lleva `TotalIVADevuelto` con la fórmula y el medio de pago `02`.

Cada rechazo de Hacienda se corrige en la tarea que lo produjo y se vuelve a enviar ese mismo caso. No se da por cerrada la tarea 8 con un caso aceptado y el resto sin enviar.

---

## Cobertura del comunicado

| Pedido de Hacienda | Tarea |
|---|---|
| Cédula jurídica alfanumérica y clave con letras | 4 |
| Código 12, efecto en el período de la nota | 1 y 2 |
| Códigos 13 y 14, efecto en el período del comprobante original | 1 y 2 |
| Código 15 | 1 y 3 |
| Código 16 y tipo de documento 10 | 3 |
| Código 17 | 6 |
| Tipo de documento 19 | 5 |
| Tipo de documento 20 | 6 |
| Teléfono corto | Ya cumple. Caso 911 en la tarea 8 |
| IVA devuelto, servicios de salud con tarjeta | 7 |
| Devolución de mercancía código 06 | Ya cumple. La tarea 2 no lo cambia |
