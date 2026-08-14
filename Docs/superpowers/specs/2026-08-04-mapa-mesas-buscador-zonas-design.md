# Design: Mapa de mesas — tamaño móvil + buscador de zonas

**Fecha:** 2026-08-04  
**Estado:** Aprobado e implementado  
**Alcance:** Vista `restaurante` (mapa de mesas).

## Objetivos

1. Mesas más compactas en móvil (~4 por fila), intermedio en tablet, cercano al tamaño actual en desktop.
2. Buscador de zonas bajo el título: filtro en vivo por nombre + botón limpiar.

## Decisiones

| Decisión | Elección |
|----------|----------|
| Filtro | Live `includes` case-insensitive + limpiar |
| Densidad | Responsive CSS `minmax` 72 / 100 / 120 |
| Archivos | Solo `restaurante.component.{ts,html,css}` |

## Fuera de alcance

- Chips de zonas, autocomplete, cambios de API.
