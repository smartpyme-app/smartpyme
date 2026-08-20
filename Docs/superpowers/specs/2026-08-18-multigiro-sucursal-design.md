# Diseño: Multigiro por sucursal (SP-2125)

**Fecha:** 2026-08-18  
**Estado:** Aprobado en brainstorming  
**Jira:** [SP-2125](https://smartpyme.atlassian.net/browse/SP-2125)  
**Origen:** alcance de Multigiro partido de [SP-2078](https://smartpyme.atlassian.net/browse/SP-2078) (multimoneda CR)

---

## 1. Problema

Una empresa puede estar inscrita en Hacienda con varias actividades económicas. SmartPyme solo guarda un giro en `empresas` (`cod_actividad_economica` + `giro`). El DTE/FE siempre usa ese valor.

Las empresas que facturan en distintos giros lo hacen por establecimiento. La sucursal ya es el punto de emisión (`cod_estable_mh`, `codigo_punto_venta`).

## 2. Decisión

- Empresa: un giro, sin cambio.
- Sucursal: giro **opcional**. Vacío → hereda empresa. Con valor → ese giro en el documento.
- No hay selector de giro en el POS.
- No hay catálogo “secundaria/tercera” en empresa.
- No hay reporte por giro en este ticket.

## 3. Catálogo en el combo de sucursal

| País | Fuente |
| --- | --- |
| El Salvador | Catálogo `actividades_economicas` (igual que empresa) |
| Costa Rica | Hacienda `GET /fe/ae` con el NIT de la empresa (igual que empresa) |
| Honduras y resto | Texto libre `giro` (igual que empresa hoy) |

## 4. Datos

Nuevos campos nullable en `sucursales`:

- `cod_actividad_economica` string(15) — SV y CR
- `giro` text — descripción; único campo en HN y otros

Sucursales existentes quedan null y siguen emitiendo con el giro de empresa.

## 5. Resolución al emitir

Helper `ActividadEconomicaEmisor::resolver($empresa, $sucursal)`:

- Si sucursal tiene código o texto no vacío → usar esos campos; el que falte se completa con empresa.
- Si no → código y giro de empresa.

Lo usan DTE SV (modelos `MH*`), FE CR (`CostaRicaInvoiceFromVentaMapper::emisor` en venta/compra/gasto) y tickets DTE de la venta.

Corte X/Z y tickets genéricos de empresa se quedan con el giro de empresa.

## 6. UI

En el modal de sucursal, junto a establecimiento / punto de venta. Placeholder: usar giro de la empresa. Combo clearable. La tabla de sucursales no muestra la columna.

## 7. Fuera de alcance

- Override de giro por documento/venta
- Reportes o filtros por giro
- Consulta SAR Honduras de actividades por RTN
- Cambiar el giro de cliente/proveedor
