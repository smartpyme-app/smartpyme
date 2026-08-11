# Diseño: Tipo de cambio en `pais_configuracion` (cache del día)

**Fecha:** 2026-08-10  
**Estado:** Aprobado (brainstorming 2026-08-10)  
**Relacionado:** `2026-08-03-cr-multimoneda-design.md` (supersede §7.1 tabla `bccr_tipos_cambio`)

## 1. Problema

La multimoneda CR creó `bccr_tipos_cambio` como cache diario BCCR. Eso se confunde con historial contable y no sirve bien a HN (oficial + editable / semi-fijo). El historial de TC usado ya vive en el documento (`exchange_rate` + `exchange_rate_date`).

## 2. Decisiones

| Tema | Decisión |
|------|----------|
| Tabla `bccr_tipos_cambio` | **Eliminar** (no está en producción; borrar migración, sin rollback) |
| Cache del TC del día | En `pais_configuracion` módulo `moneda` → `rate_del_dia` |
| Historial | Solo en documentos transaccionales |
| Sin API / API falla | Usar `rate_manual` si existe; si no, bloquear |
| Job | Uno multi-país: sync del día solo donde `fuente=api` |
| Fechas pasadas | Consultar API on-demand; **no** acumular en config |

## 3. Forma de `pais_configuracion` (módulo `moneda`)

```json
{
  "moneda_funcional": "CRC",
  "monedas_documento": ["CRC", "USD"],
  "fuente": "api",
  "api": { "provider": "bccr" },
  "rate_del_dia": {
    "date": "2026-08-10",
    "from": "USD",
    "to": "CRC",
    "rate": 512.34,
    "fetched_at": "2026-08-10T06:00:00-06:00"
  },
  "rate_manual": null,
  "permitir_editar": false
}
```

- **CR:** `fuente=api`, `api.provider=bccr`, `rate_manual` opcional como fallback.
- **HN:** `fuente=manual`, `rate_manual` semi-fijo, `permitir_editar` según producto; sin API por ahora.

## 4. Resolución

1. Si `rate_del_dia.date` == fecha pedida y `rate > 0` → usar cache.
2. Si `fuente=api` → fetch provider; si fecha == hoy → escribir `rate_del_dia`.
3. Si falla / sin API → `rate_manual` si `> 0`; si no → error (no inventar tasa).
4. Override manual en venta solo con flag de empresa/país → se congela en el documento.

## 5. Job

Comando `tipos-cambio:sync-dia`: recorre filas `modulo=moneda` con `fuente=api`, resuelve el provider y cachea el TC de hoy. Schedule diario (timezone por país cuando aplique; CR `America/Costa_Rica` 06:00).

## 6. Fuera de alcance

- Generalizar tabla histórica multi-país.
- API Honduras.
- Cambiar columnas de ventas/compras/gastos ya definidas en multimoneda CR.
