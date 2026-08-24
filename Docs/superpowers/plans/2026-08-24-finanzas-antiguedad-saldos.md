# Antigüedad de saldos Finanzas — Implementation Plan

> **For agentic workers:** Execute task-by-task. Steps use checkbox syntax.

**Goal:** Reporte unificado CxC/CxP de antigüedad de saldos en Finanzas (pantalla, PDF, Excel), sin romper Ventas/Compras.

**Architecture:** `AntiguedadSaldosService` agrega saldos pendientes con buckets contables; controller + rutas; pantalla Angular estilo CxC; menú Finanzas.

**Tech Stack:** Laravel, DomPDF, Maatwebsite Excel, Angular standalone.

## Global Constraints

- No modificar `estadoCuenta` clientes ni listados CxC/CxP existentes.
- Saldo operativo (Pendiente, cotizacion=0); buckets nota05.
- Permiso `finanzas.reporteria.ver`.

## Tasks

- [x] Task 1: Service + unit test buckets/saldo
- [x] Task 2: Controller, routes, PDF blade, Excel export
- [x] Task 3: Angular screen + routing
- [x] Task 4: Sidebar Finanzas (antigüedad + enlaces CxC/CxP)
- [x] Task 5: Verify unit tests
