# Manual de pruebas (QA / UAT) — Consignaciones v2

> Completar con resultados y capturas al finalizar desarrollo.

## Pre-requisitos

- Backend y Frontend desplegados en staging.
- Migraciones y `FuncionalidadesSeeder` ejecutados (`MIGRATIONS_AND_SEEDERS.md`).
- Dos empresas de prueba: **A** (sin módulo), **B** (con módulo).

## 1. Regresión — Empresa A (sin módulo)

| # | Paso | Resultado esperado | OK |
|---|------|-------------------|-----|
| 1.1 | Compra normal pagada con producto inventariable | Stock ↑; kardex "Compra" | |
| 1.2 | Venta normal pagada | Stock ↓; kardex "Venta" | |
| 1.3 | Consultar kardex producto | Mismos totales que antes del release | |
| 1.4 | `SELECT COUNT(*) FROM consigna_movimientos WHERE id_empresa = A` | 0 | |

## 2. Activación — Empresa B

| # | Paso | Resultado esperado | OK |
|---|------|-------------------|-----|
| 2.1 | Super Admin → asignar `modulo-consignaciones` | Funcionalidad activa | |
| 2.2 | Mi cuenta → activar ledger consigna | Guardado en preferencias | |
| 2.3 | Usuario con permiso `inventario.consigna_kardex.ver` | Menú Kardex consigna visible | |

## 3. Entrada consigna proveedor

| # | Paso | Resultado esperado | OK |
|---|------|-------------------|-----|
| 3.1 | Compra estado Consigna, 10 u. | Stock +10; kardex clásico; 1 movimiento `entrada_proveedor` por línea | |
| 3.2 | Kardex consigna UI | Muestra entrada con proveedor y costo | |

## 4. Venta desde pool consigna

| # | Paso | Resultado esperado | OK |
|---|------|-------------------|-----|
| 4.1 | Venta regular, modal "Consigna (compra)", 4 u. | Stock −4; movimiento venta consigna proveedor | |
| 4.2 | Pool disponible API | Coherente con ledger (Fase 1 puede divergir leve → log debug) | |

## 5. Liquidación proveedor

| # | Paso | Resultado esperado | OK |
|---|------|-------------------|-----|
| 5.1 | Revisar consigna, pagar 4 u. | Compra pagada; movimiento `liquidacion_proveedor`; **stock sin cambio** vs paso 4.1 | |

## 6. Consigna salida a cliente

| # | Paso | Resultado esperado | OK |
|---|------|-------------------|-----|
| 6.1 | Venta consigna + remisión | Movimiento entrega cliente; kardex físico como hoy | |
| 6.2 | Facturar parcial consigna | `liquidacion_cliente`; sin segunda salida stock | |

## 7. Permisos

| # | Paso | Resultado esperado | OK |
|---|------|-------------------|-----|
| 7.1 | Usuario sin permiso kardex consigna | 403 en API | |
| 7.2 | Exportar Excel con `inventario.consigna_kardex.exportar` | Descarga `.xlsx`; sin permiso no muestra botón / 403 API | |

## Firmas UAT

- Fecha:
- Empresa piloto:
- Aprobado por:
