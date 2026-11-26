# Task 4 — Report

**Status:** DONE_WITH_CONCERNS

## Entregado
- `Backend/app/Services/Ventas/EjecutarVentasProgramadasService.php` — `ejecutar(Carbon $now): int`
- `Backend/app/Console/Commands/EjecutarVentasProgramadas.php` — `ventas:ejecutar-programadas`
- `Backend/app/Console/Kernel.php` — schedule `everyFifteenMinutes()->withoutOverlapping()` → `storage/logs/ventas-programadas.log` (línea de recurrentes intacta)
- Commit: `feat: cron facturas programadas`

## Flujo
1. Selecciona `activo` con `proxima_ejecucion <= now`. Si max alcanzado → `activo=false`.
2. `periodo` por `ProgramacionCalendario::periodo`. Si ya hay ejecución `ok` del periodo → historial `omitida`, avanza.
3. Crea venta con `FacturacionService::assertReglasNegocio` + `procesar` (mismo patrón que recurrentes, Request interno con `ATTR_VENTAS_RECURRENTES_CRON`), `estado='Pendiente'`, `id_venta_programada`.
4. `emitir_automatico`: solo si empresa tiene FE y es SV → `emitir` de recurrentes. Si no aplica → venta creada, `modo=borrador` con aviso.
5. Email al cliente SOLO si emitió y `enviar_email`; fallo de correo no invalida (queda en `mensaje_error`).
6. Historial `ok|error|omitida`, `modo emitida|borrador`. En error: `estado=error`, count no sube, **igual avanza** `proxima_ejecucion` (sin reintento 15 min; salta periodos vencidos, sin backfill).
7. Tras `ok`, si `ejecuciones_count >= max_ejecuciones` → `activo=false`.

## Verificación
- `php -l` OK; `php artisan ventas:ejecutar-programadas` → "procesadas: 0"; `schedule:list` muestra `*/15 * * * *`.
- NO se probó end-to-end con fila real (requiere cliente/producto/documento/FE).

## Concerns
- `GenerarVentasRecurrentesService` NO modificado; sus métodos privados (`emitir`, `enviarFacturaAlCliente`, `ejecutarComoUsuario`) se invocan por **reflexión**. Frágil ante renombres; upgrade: extraer servicio compartido de emisión DTE SV.
- Venta siempre `Pendiente` (la plantilla no guarda método de pago); gift cards/multimoneda de recurrentes no replicados.
- Sin lock de fila; la exclusión depende de `withoutOverlapping`.
- Sin test automatizado de idempotencia (requeriría DB).
- graphify CLI no disponible; se leyó el código directo.


## Fix review (Critical/Important)

- Reflexión eliminada: `emitir`, `enviarFacturaAlCliente`, `ejecutarComoUsuario` ahora `public` en `GenerarVentasRecurrentesService` (solo visibilidad + docblock). `EjecutarVentasProgramadasService` los llama directo.
- Claim atómico: nuevo `reclamar()` hace `UPDATE ... WHERE id AND activo AND proxima_ejecucion = valor leído` avanzando `proxima_ejecucion` ANTES de crear la venta; 0 filas => se omite. Tras el claim, errores dejan historial y el slot no se reprocesa cada 15 min. (Limitación: crash duro entre claim e historial pierde ese slot, sin duplicar.)
- `id_venta` se registra en historial aun si `emitir` falla (se asigna justo tras `procesar`).
- Test nuevo: `Backend/tests/Unit/Services/Ventas/EjecutarVentasProgramadasApiPublicaTest.php` (métodos públicos).

### Tests
`php artisan test --filter=ProgramacionCalendarioTest` -> PASS, 4 passed (5 assertions)
`php artisan test --filter=EjecutarVentasProgramadasApiPublicaTest` -> PASS, 2 passed (4 assertions)
