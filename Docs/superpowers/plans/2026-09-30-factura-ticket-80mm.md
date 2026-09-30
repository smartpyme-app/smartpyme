# Factura y ticket 80 mm Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un switch en Facturación hace que Factura y Ticket comerciales se impriman en una plantilla nueva de 80 mm, con CAI y correlativo solo en Honduras.

**Architecture:** `Ticket80mm::aplica()` concentra la regla. Los tres puntos de impresión la consultan y, si aplica, llaman `Ticket80mm::imprimir()`. La vista `ticket-80mm` no reutiliza la de Santre.

**Tech Stack:** Laravel, Blade, dompdf, Angular (preferencias de empresa).

## Global Constraints

- Clave `imprimir_factura_ticket_80mm`, default `false`.
- Documentos: Factura, Ticket, Factura con RTN, Factura sin RTN.
- No aplica a DTE, Factura Electrónica, Tiquete Electrónico, recibo, cotización, notas.
- Empresas que conservan su plantilla: 818, 716, 420, 614, 700, y el flag `factura_ticket_accesorios_hn`.
- `ticket_en_pdf` sigue decidiendo PDF o ventana de impresión.
- Sin CAI, rango, logo o cliente, esa línea no se imprime.

---

### Task 1: Decisión y prueba

**Files:**
- Create: `Backend/app/Support/Ventas/Ticket80mm.php`
- Test: `Backend/tests/Unit/Support/Ventas/Ticket80mmTest.php`

- [ ] Escribir `Ticket80mmTest` (switch, documentos, empresas excluidas, HTML HN y no HN).
- [ ] Implementar `aplica()` e `imprimir()`.
- [ ] `php artisan test --filter=Ticket80mmTest` pasa.

### Task 2: Enganchar impresión

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Ventas/GenerarDocumentosController.php`
- Modify: `Backend/app/Http/Controllers/Api/Ventas/VentasController.php`
- Modify: `Backend/app/Services/Ventas/DocumentoService.php`

- [ ] Después de cargar venta y documento, y después del retorno de facturación electrónica, si `aplica()` devolver `imprimir()`.

### Task 3: Switch de preferencias

**Files:**
- Modify: `Backend/app/Models/Admin/Empresa.php`
- Modify: `Backend/app/Http/Controllers/Api/Admin/EmpresasController.php`
- Modify: `Backend/app/Http/Requests/Admin/Empresas/UpdateCustomConfigRequest.php`
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.ts`
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.html`

- [ ] Default `false`, allowlist booleana, fila en el grupo Facturación.
