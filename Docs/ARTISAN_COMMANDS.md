# Comandos Artisan — SmartPyme Backend

Documentación de referencia para desarrollo y operaciones. Generada desde el código en `Backend/app/Console/Commands`.

**Total de comandos propios:** 72 (excluye comandos nativos de Laravel).

### Publicar en Confluence

1. Crear una página en el espacio del equipo.
2. Usar el macro **Markdown** (o *Insert markup* → Markdown) y pegar el contenido de este archivo.
3. Alternativa: adjuntar el `.md` o convertir con una herramienta de importación Markdown → Confluence.
4. Tras agregar comandos nuevos en código, regenerar con `php scripts/generate-artisan-commands-doc.php`.

---

## Cómo ejecutar

Desde la raíz del backend (mismo entorno y `.env` que la API que consultas):

```bash
cd Backend
php artisan list                    # listado completo
php artisan <comando> --help        # ayuda de un comando
php artisan fidelizacion:reset-empresa 756 --dry-run
```

En **producción**, ejecutar dentro del contenedor/servidor donde corre la API (misma base de datos que usa el frontend).

Convenciones frecuentes:

| Opción | Significado |
|--------|-------------|
| `--dry-run` | Simula o muestra conteos sin escribir |
| `--force` | Omite confirmación interactiva |
| `--empresa=` | Filtra por ID de empresa |

---

## Comandos programados (cron / scheduler)

Definidos en `app/Console/Kernel.php`. En producción el scheduler de Laravel debe estar activo (`* * * * * php artisan schedule:run`).

| Comando | Frecuencia | Notas |
|---------|------------|-------|
| `generate:notificaciones` | Diario | Notificaciones del sistema |
| `reportes:enviar` | Cada 5 min | Encola reportes automáticos configurados |
| `auditoria:purge` | Día 1 de cada mes, 04:00 | Purga auditoría antigua |
| `reportes:limpiar-exportaciones` | Diario 03:30 | Limpia exportaciones de reportes |
| `metricas:empresas` | Diario 03:00 | Métricas por empresa |
| `metricas:sucursales` | Diario 04:00 | Métricas por sucursal |
| `metricas:empresas --actualizar-historico` | Día 1, 02:00 | Histórico mensual empresas |
| `metricas:sucursales --actualizar-historico` | Día 1, 02:30 | Histórico mensual sucursales |
| `empleados:actualizar-estado` | Diario 00:01 | Estado de empleados |
| `suscripciones:verificar` | Diario 01:00 | Estado de suscripciones y trials |
| `facturas:generar-suscripciones` | Día 1, 08:00 | DTE de suscripciones por transferencia |
| `ventas:generar-recurrentes` | Diario 06:00 (America/El_Salvador) | Ventas recurrentes del día |
| `suscripciones:enviar-recordatorios-correo` | Diario 08:00 | Recordatorios de suscripción |
| `prestamos:recordatorios-vencimiento` | Diario 07:00 | Cuotas de préstamo por vencer |
| `suscripciones:reportes-internos-equipo --solo=diario` | Diario 08:00 | Reporte interno equipo (diario) |
| `suscripciones:reportes-internos-equipo --solo=semanal` | Viernes 08:00 | Reporte interno equipo (semanal) |
| `suscripciones:reporte-flujo-caja-mensual` | Día 1, 08:15 | Flujo de caja suscripciones |
| `suscripciones:reporte-bajas-mensual` | Día 1, 08:30 | Bajas de suscripción |
| `reporte:ventas-por-categoria-sucursal` | Diario 08:00 | Ventas por categoría y sucursal |
| `cliente360:actualizar-agregados --masivo` | Cada minuto | Agregados Cliente 360 |
| `fidelizacion:procesar-expiracion-puntos --sync` | Diario 02:00 | Expiración de puntos |
| `bonos:evaluar` | Diario 02:30 | Evaluación de bonos vendedores |
| `activos:depreciacion-mensual` | Diario 05:00 | Depreciación activos fijos |
| `dte:migrate-to-s3` | Diario 02:45 (si config dte.schedule_enabled) | Migración DTE a S3 |
| `dte:sync-accounts --dias=30` | Cada hora | Sync buzones DTE |
| `dte:ingest-spool --limit=50` | Cada minuto | Procesa spool de correos DTE |
| `dte:ingest-purge --days=7` | Diario 03:20 | Purga spool DTE |
| `tipos-cambio:sync-dia` | Diario 06:00 (America/Costa_Rica) | Tipo de cambio del día |

---

## Comandos con impacto destructivo o irreversible

Usar solo con `--dry-run` primero, backup de BD y acuerdo del equipo:

- **`fidelizacion:reset-empresa`** — Pone en cero los datos de fidelización de una empresa/licencia (no elimina ventas ni clientes)
- **`fidelizacion:procesar-ventas-existentes`** — Procesa las ventas existentes que no tienen puntos generados para generar puntos de fidelización
- **`ventas:eliminar-rango`** — Elimina ventas y sus registros relacionados en el rango de IDs especificado
- **`compras:eliminar-rango`** — Elimina compras y sus registros relacionados en el rango de IDs especificado
- **`clientes:fusionar-duplicados`** — Fusiona clientes inhabilitados hacia el habilitado con el mismo NIT o DUI y los elimina
- **`dte:migrate-to-s3`** — Sube DTE (JSON El Salvador o XML Costa Rica) desde compras/ventas a S3 y vacía la columna local.
- **`imagenes:migrate-to-s3`** — Sube imágenes de productos desde el VPS a S3 y elimina copias locales tras éxito.
- **`compras:sync-productos`** — Sincroniza productos de compras entre bases: exporta desde sp_nova, importa y actualiza detalles en DB_DATABASE

---

## Catálogo por módulo

### Activos fijos

#### `activos:depreciacion-mensual`

Ejecuta la corrida mensual de depreciación de activos fijos por empresa

```bash
php artisan activos:depreciacion-mensual {--empresa= : ID de empresa (opcional)} {--periodo= : Período YYYY-MM (default: mes anterior)} {--dry-run : Solo preview, sin ejecutar corrida} {--forzar : Ignorar día de corte configurado}
```

Archivo: `app/Console/Commands/ActivosDepreciacionMensualCommand.php`

### Atributos de producto

#### `attributes:extract-from-products`

Extract and create attributes from existing products

```bash
php artisan attributes:extract-from-products
```

Archivo: `app/Console/Commands/ExtractProductAttributes.php`

### Auditoría

#### `auditoria:purge`

Elimina registros de auditoría más antiguos que el período de retención

```bash
php artisan auditoria:purge {--months=}
```

Archivo: `app/Console/Commands/PurgeAuditsCommand.php`

### Autorizaciones

#### `authorizations:expire`

Expire old pending authorizations

```bash
php artisan authorizations:expire
```

Archivo: `app/Console/Commands/Authorization/ExpireAuthorizations.php`

### Bonos comerciales

#### `bonos:evaluar`

Evalúa bonos de vendedores según reglas activas y ventas del período

```bash
php artisan bonos:evaluar {--empresa=} {--desde=} {--hasta=}
```

Archivo: `app/Console/Commands/EvaluarBonosVendedoresCommand.php`

### Certificados / SFTP

#### `certificado:test-conexion`

Verifica la conexión SFTP al disco uploads_ec2 (certificados EC2)

```bash
php artisan certificado:test-conexion {--verificar= : Archivo en la raíz remota a comprobar (ej. 06141234561234.crt)}
```

Archivo: `app/Console/Commands/CertificadoTestConexionCommand.php`

### Cliente 360

#### `cliente360:actualizar-agregados`

Actualiza las tablas agregadas para Cliente360

```bash
php artisan cliente360:actualizar-agregados {--cliente= : ID específico de cliente} {--tipo= : Tipo de agregado (rfm, productos, mensuales, fidelizacion, actividad, categorias, all)} {--masivo : Procesamiento masivo ultra-rápido (recomendado)} {--force : Forzar actualización aunque esté reciente}
```

Archivo: `app/Console/Commands/cliente360/CalcularClientes360Command.php`

### Clientes

#### `clientes:fusionar-duplicados`

Fusiona clientes inhabilitados hacia el habilitado con el mismo NIT o DUI y los elimina

```bash
php artisan clientes:fusionar-duplicados {--empresa= : ID de empresa (obligatorio)} {--ejecutar : Aplica cambios. Sin esto solo muestra el plan}
```

Archivo: `app/Console/Commands/FusionarClientesDuplicadosCommand.php`

#### `clientes:refrescar-plantillas-importacion`

Plantilla empresas: una fila de ejemplo genérica, hoja Instrucciones; personas: columna código de cliente

```bash
php artisan clientes:refrescar-plantillas-importacion
```

Archivo: `app/Console/Commands/RefrescarPlantillasImportacionClientesCommand.php`

### Cola de trabajos

#### `trabajos:procesar`

Procesa los trabajos pendientes en la base de datos. Usa --solo-imagenes-shopify para procesar solo jobs de imágenes de Shopify

```bash
php artisan trabajos:procesar {--limite=5} {--duracion=58} {--id=} {--solo-imagenes-shopify}
```

Archivo: `app/Console/Commands/ProcesarTrabajosPendientes.php`

### Compras

#### `compras:eliminar-rango`

Elimina compras y sus registros relacionados en el rango de IDs especificado

```bash
php artisan compras:eliminar-rango {desde=28316 : ID inicial} {hasta=28347 : ID final}
```

Archivo: `app/Console/Commands/EliminarComprasRango.php`

#### `compras:importar-datos`

Importa compras y detalles desde archivos PHP en la carpeta datos, actualizando inventario y kardex

```bash
php artisan compras:importar-datos {--ruta-compras=datos/compras.php : Ruta al archivo compras.php} {--ruta-detalles=datos/detalles_compra.php : Ruta al archivo detalles_compra.php} {--solo-insertar : Solo insertar datos, no actualizar inventario/kardex}
```

Archivo: `app/Console/Commands/ImportarComprasDatos.php`

#### `compras:sync-productos`

Sincroniza productos de compras entre bases: exporta desde sp_nova, importa y actualiza detalles en DB_DATABASE

```bash
php artisan compras:sync-productos {--ids= : IDs de compras separados por coma (ej: 28401,28440,28389)} {--exportar : Exportar productos a archivo PHP para producción} {--archivo= : Archivo PHP a importar (ejecutar en prod)} {--ruta=datos/compras : Carpeta para archivos de export/import} {--generar-sql : Al exportar, también genera scripts SQL (productos + imagenes)} {--sin-inventario : No actualizar inventario ni kardex (útil para pruebas)} {--dry-run : Solo mostrar qué se haría, no escribir}
```

Archivo: `app/Console/Commands/SincronizarProductosCompras.php`

### Configuración de empresa

#### `empresa-config:migrar`

Migra empresa_configuracion_planilla y custom_empresa a empresa_configuracion

```bash
php artisan empresa-config:migrar {--dry-run : Solo reportar, sin escribir}
```

Archivo: `app/Console/Commands/MigrarEmpresaConfiguracionCommand.php`

### Contabilidad

#### `contabilidad:cerrar-mes`

Ejecuta el cierre de mes contable para una o múltiples empresas

```bash
php artisan contabilidad:cerrar-mes {--empresa=* : IDs de las empresas a procesar} {--mes= : Mes a cerrar (1-12)} {--anio= : Año a cerrar} {--usuario= : ID del usuario que ejecuta el cierre} {--forzar : Forzar cierre aunque haya validaciones} {--dry-run : Simular el cierre sin ejecutar}
```

Archivo: `app/Console/Commands/CerrarMesContable.php`

### DTE / facturación electrónica

#### `dte:actualizar-correo-emisor`

Corrige emisor.correo en DTE (columna local y/o objeto S3) si no coincide con el correo indicado

```bash
php artisan dte:actualizar-correo-emisor {--id-empresa= : ID de la empresa (obligatorio)} {--correo= : Correo correcto del emisor en el JSON (obligatorio)} {--dry-run : Solo mostrar qué se actualizaría, sin escribir} {--table=ventas : ventas, compras o both} {--mes= : Filtrar por mes de la columna fecha (YYYY-MM, ej. 2025-06)} {--desde= : Inicio de rango fecha (Y-m-d); usar junto con --hasta} {--hasta= : Fin de rango fecha (Y-m-d); usar junto con --hasta} {--skip-invalidacion : No revisar dte_invalidacion} {--limit= : Máximo de filas a revisar por tabla}
```

Archivo: `app/Console/Commands/ActualizarCorreoEmisorDteCommand.php`

#### `dte:actualizar-correo-emisor-sagrivet`

Actualiza el correo del emisor en dte y dte_invalidacion de ventas de Sagrivet (empresa 675) desde mayo

```bash
php artisan dte:actualizar-correo-emisor-sagrivet {--dry-run : Solo mostrar qué se actualizaría, sin ejecutar} {--id-empresa=675 : ID de la empresa (Sagrivet)} {--desde=2026-05-01 : Fecha mínima inclusive (Y-m-d)} {--correo-anterior=vetbanimal@gmail.com : Correo del emisor a reemplazar} {--correo-nuevo=sagrivet.contabilidad@gmail.com : Correo del emisor destino}
```

Archivo: `app/Console/Commands/ActualizarCorreoEmisorDteSagrivet.php`

#### `dte:inbox-create`

Activa una dirección token@ingest.smartpyme.site para una empresa

```bash
php artisan dte:inbox-create {id_empresa} {--user_id= : Usuario dueño de la cuenta sintética} {--token= : Token fijo (pruebas); si se omite es aleatorio}
```

Archivo: `app/Console/Commands/CreateDteEmailInbox.php`

#### `dte:ingest-purge`

Elimina .eml/.meta antiguos del spool de ingest

```bash
php artisan dte:ingest-purge {--days=7 : Borrar archivos del spool más viejos que N días}
```

Archivo: `app/Console/Commands/IngestDteSpoolPurgeCommand.php`

#### `dte:ingest-spool`

Procesa correos del spool de ingest hacia el pipeline DTE existente

```bash
php artisan dte:ingest-spool {--limit=50 : Máximo de .eml por corrida}
```

Archivo: `app/Console/Commands/IngestDteSpoolCommand.php`

#### `dte:migrate-to-s3`

Sube DTE (JSON El Salvador o XML Costa Rica) desde compras/ventas a S3 y vacía la columna local.

```bash
php artisan dte:migrate-to-s3 {--dry-run : No escribe en S3 ni actualiza la base de datos} {--limit= : Máximo de filas (registros) a revisar por tabla} {--table=both : ventas, compras o both} {--skip-invalidacion : No migrar columnas dte_invalidacion} {--mes= : Filtrar por mes de la columna fecha (formato YYYY-MM, ej. 2025-06)} {--desde= : Inicio de rango fecha (Y-m-d); usar junto con --hasta} {--hasta= : Fin de rango fecha (Y-m-d); usar junto con --desde}
```

Archivo: `app/Console/Commands/MigrateDteToS3Command.php`

#### `dte:sync-accounts`

Sincroniza cuentas de correo activas del módulo DTE (descarga DTEs)

```bash
php artisan dte:sync-accounts {--dias=30 : Días hacia atrás para buscar correos}
```

Archivo: `app/Console/Commands/SyncDteEmailAccounts.php`

### Exportaciones catálogo

#### `export:actividades`

Exportar actividades económicas

```bash
php artisan export:actividades {--format=csv}
```

Archivo: `app/Console/Commands/ExportActividades.php`

### Facturación SmartPyme (suscripciones)

#### `facturas:generar-suscripciones`

Emite DTE de suscripciones activas con pago por transferencia: planes mensuales el día 1 de cada mes; planes anuales en la fecha de renovación. La cadencia sale de frecuencia_pago de la empresa y, si está vacía, de tipo_plan de la suscripción. Excluye n1co (otro flujo) y empresas con campaña demo.

```bash
php artisan facturas:generar-suscripciones {empresa? : ID de la empresa para ejecutar una prueba específica}
```

Archivo: `app/Console/Commands/GenerarFacturasSuscripciones.php`

### Fidelización

#### `fidelizacion:procesar-expiracion-puntos`

Procesa los puntos de fidelización que han expirado y los marca como vencidos

```bash
php artisan fidelizacion:procesar-expiracion-puntos {--sync : Ejecutar de forma síncrona en lugar de encolar}
```

Archivo: `app/Console/Commands/ProcesarExpiracionPuntosCommand.php`

#### `fidelizacion:procesar-ventas-existentes`

Procesa las ventas existentes que no tienen puntos generados para generar puntos de fidelización

```bash
php artisan fidelizacion:procesar-ventas-existentes {--empresa=} {--limite=100} {--dry-run}
```

Archivo: `app/Console/Commands/ProcesarPuntosVentasExistentes.php`

#### `fidelizacion:reset-empresa`

Pone en cero los datos de fidelización de una empresa/licencia (no elimina ventas ni clientes)

```bash
php artisan fidelizacion:reset-empresa {empresa : ID de la empresa (padre o sucursal; incluye toda la licencia)} {--dry-run : Mostrar conteos sin modificar datos} {--force : Ejecutar sin confirmación interactiva}
```

Archivo: `app/Console/Commands/ResetFidelizacionEmpresaCommand.php`

### Generación (notificaciones, cortes)

#### `generate:cortes`

Enviar los cortes diarios a clientes automáticamente

```bash
php artisan generate:cortes
```

Archivo: `app/Console/Commands/Cortes.php`

#### `generate:notificaciones`

Generar las notificaciones

```bash
php artisan generate:notificaciones
```

Archivo: `app/Console/Commands/Notificaciones.php`

### Importación catálogos geo

#### `importar:estados`

Importa estados/provincias desde un archivo JSON

```bash
php artisan importar:estados {json_path?}
```

Archivo: `app/Console/Commands/ImportarEstadosPaises.php`

### Imágenes productos

#### `imagenes:migrate-to-s3`

Sube imágenes de productos desde el VPS a S3 y elimina copias locales tras éxito.

```bash
php artisan imagenes:migrate-to-s3 {--dry-run : No escribe en S3 ni borra archivos locales} {--limit= : Máximo de filas a procesar} {--empresa= : Solo productos de esta id_empresa} {--local-root= : Carpeta img legacy (contiene productos/); override de PRODUCT_IMAGES_LOCAL_ROOT} {--recover-from-src : Si falta el archivo local, intenta descargar desde productos_imagenes.src} {--skip-missing : No contar como error las filas sin archivo local ni S3}
```

Archivo: `app/Console/Commands/MigrateProductImagesToS3Command.php`

### Inteligencia artificial

#### `ia:costos`

Genera un reporte de costos de IA por empresa y periodo

```bash
php artisan ia:costos {--empresa=} {--desde=} {--hasta=}
```

Archivo: `app/Console/Commands/ReporteCostosIA.php`

### Inventario / análisis

#### `inventario:procesar-cola-analisis-ventas`

Procesa la cola de reportes de análisis de ventas mensual (inventario)

```bash
php artisan inventario:procesar-cola-analisis-ventas {--limit=0 : Máximo de solicitudes (0 = todas)}
```

Archivo: `app/Console/Commands/ProcesarColaAnalisisVentasMensual.php`

### Kardex

#### `kardex:generar-masivo`

Genera kardex masivo para una empresa y lo envía por correo

```bash
php artisan kardex:generar-masivo {email} {id_empresa}
```

Archivo: `app/Console/Commands/GenerarKardexMasivoCommand.php`

#### `kardex:procesar-cola`

Procesa la cola de kardex masivo en segundo plano

```bash
php artisan kardex:procesar-cola {--limit=0 : Número máximo de elementos a procesar (0 = todos)}
```

Archivo: `app/Console/Commands/ProcesarColaKardexMasivo.php`

### Migraciones custom

#### `migrate:custom`

Ejecuta migraciones específicas con barra de progreso

```bash
php artisan migrate:custom
```

Archivo: `app/Console/Commands/CustomMigrate.php`

### Métricas

#### `metricas:empresas`

Calcula y actualiza las métricas mensuales para empresas

```bash
php artisan metricas:empresas {--fecha=} {--empresa=} {--actualizar-historico}
```

Archivo: `app/Console/Commands/CalcularMetricasEmpresas.php`

#### `metricas:historico`

Carga métricas históricas desde una fecha inicial hasta el mes actual

```bash
php artisan metricas:historico {fecha_inicio} {--empresa=} {--solo-empresas} {--solo-sucursales}
```

Archivo: `app/Console/Commands/CargarMetricasHistoricas.php`

#### `metricas:sucursales`

Calcula y actualiza las métricas mensuales para sucursales

```bash
php artisan metricas:sucursales {--fecha=} {--empresa=} {--sucursal=} {--actualizar-historico}
```

Archivo: `app/Console/Commands/CalcularMetricasSucursales.php`

### Productos

#### `productos:actualizar-proveedores-compras`

Asigna proveedor a productos según las compras de la empresa (prevalece la compra más reciente)

```bash
php artisan productos:actualizar-proveedores-compras {id_empresa : ID de la empresa} {--dry-run : Mostrar cambios sin aplicarlos}
```

Archivo: `app/Console/Commands/ActualizarProveedoresProductosDesdeCompras.php`

#### `productos:asignar-impuesto`

A todos los productos sin impuesto les asigna el IVA de su empresa (por id_empresa)

```bash
php artisan productos:asignar-impuesto
```

Archivo: `app/Console/Commands/AsignarImpuestoProductosEmpresa.php`

### Préstamos

#### `prestamos:recordatorios-vencimiento`

Envía correos de recordatorio de cuotas de préstamo próximas a vencer

```bash
php artisan prestamos:recordatorios-vencimiento {--dias=2 : Días antes del vencimiento} {--dry-run : No enviar correos ni escribir caché}
```

Archivo: `app/Console/Commands/EnviarRecordatoriosPrestamoCuota.php`

### Reportes automáticos

#### `reportes:enviar`

Encola los reportes automáticos configurados

```bash
php artisan reportes:enviar {--tipo= : Tipo específico de reporte} {--force : Forzar envío sin considerar cache}
```

Archivo: `app/Console/Commands/EnviarReportesAutomaticos.php`

#### `reportes:limpiar-exportaciones`

Elimina exportaciones de reportes terminadas/fallidas y sus archivos temporales

```bash
php artisan reportes:limpiar-exportaciones {--horas=24 : Antigüedad mínima en horas}
```

Archivo: `app/Console/Commands/LimpiarReporteExportaciones.php`

### Reportes puntuales

#### `reporte:ventas-por-categoria-sucursal`

Reporte de ventas por categoría (Productos 100%, Servicios 90%) agrupado por sucursal para empresas seleccionadas.

```bash
php artisan reporte:ventas-por-categoria-sucursal {--inicio= : Fecha inicio YYYY-MM-DD} {--fin= : Fecha fin YYYY-MM-DD} {--dry-run : Generar Excel y guardarlo en storage sin enviar correo}
```

Archivo: `app/Console/Commands/EnviarReporteVentasPorCategoriaSucursal.php`

#### `reporte:ventas-por-vendedor`

Envía el reporte diario de ventas por vendedor por correo.

```bash
php artisan reporte:ventas-por-vendedor
```

Archivo: `app/Console/Commands/EnviarReporteVentasPorVendedor.php`

### Restaurante

#### `restaurante:importar-mesas`

Importa mesas de restaurante desde Excel y las asigna a zonas existentes por nombre

```bash
php artisan restaurante:importar-mesas {archivo : Ruta al archivo xlsx} {--empresa= : ID de empresa (obligatorio)} {--dry-run : Solo validar y reportar, sin escribir}
```

Archivo: `app/Console/Commands/ImportarMesasRestaurante.php`

### Shopify

#### `shopify:backfill-variantes`

Rellena columnas de variantes Shopify (shopify_sku, option*_value/name) en productos existentes

```bash
php artisan shopify:backfill-variantes {--empresa=} {--dry-run} {--with-names}
```

Archivo: `app/Console/Commands/BackfillShopifyVariantsCommand.php`

#### `shopify:dedupe-variantes`

Consolida filas duplicadas de productos con el mismo shopify_variant_id (1 sola fila por variante)

```bash
php artisan shopify:dedupe-variantes {--empresa=} {--dry-run}
```

Archivo: `app/Console/Commands/DedupeShopifyVariantsCommand.php`

#### `shopify:procesar-trabajos`

Procesar trabajos pendientes de importación Shopify

```bash
php artisan shopify:procesar-trabajos {--lote=10} {--procesar-productos-shopify}
```

Archivo: `app/Console/Commands/ImportarShopifyCommand.php`

#### `shopify:sync-productos`

Sincroniza productos y stock desde Shopify hacia SmartPyme (upsert + dedupe por variant_id)

```bash
php artisan shopify:sync-productos {--empresa= : ID de la empresa} {--bodega= : ID de bodega para el stock (default: primera bodega activa)} {--desactivar-ausentes : Desactivar variantes locales que ya no existen en Shopify} {--sin-kardex : No registrar movimientos de kardex durante la sincronización} {--dry-run : Solo reporta sin escribir}
```

Archivo: `app/Console/Commands/ShopifySyncCommand.php`

### Suscripciones (alertas UI)

#### `subscription:update-alerts`

Actualiza alerta_suscripcion (banners: admin días 3 y 1; todos 0..-N; bloqueo -N-1)

```bash
php artisan subscription:update-alerts {--dry-run : Ejecutar sin hacer cambios}
```

Archivo: `app/Console/Commands/UpdateSubscriptionAlerts.php`

### Suscripciones SmartPyme

#### `suscripciones:enviar-recordatorios-correo`

Envía correos de recordatorio/alerta según días respecto a fecha_proximo_pago (3, 1, 0, -2)

```bash
php artisan suscripciones:enviar-recordatorios-correo {--dry-run : No enviar correos ni escribir caché}
```

Archivo: `app/Console/Commands/EnviarRecordatoriosSuscripcionCorreo.php`

#### `suscripciones:procesar-cargos`

Procesa los cargos automáticos de las suscripciones activas

```bash
php artisan suscripciones:procesar-cargos {--force : Forzar procesamiento sin importar la fecha}
```

Archivo: `app/Console/Commands/ProcesarCargosAutomaticos.php`

#### `suscripciones:reporte-bajas-mensual`

Reporte mensual de bajas de suscripción (HTML + Excel): mes, histórico 12m y proyección resto del año.

```bash
php artisan suscripciones:reporte-bajas-mensual {--mes= : Mes en formato YYYY-MM (por defecto: mes calendario anterior)} {--dry-run : Generar Excel y no enviar correo} {--backfill : Importar cancelaciones existentes con fecha_cancelacion sin fila en histórico}
```

Archivo: `app/Console/Commands/EnviarReporteBajasMensualSuscripcion.php`

#### `suscripciones:reporte-flujo-caja-mensual`

Reporte mensual de flujo de caja (Excel): pagos esperados del 1 al 15 y del 16 al fin de mes, por fecha de próximo pago.

```bash
php artisan suscripciones:reporte-flujo-caja-mensual {--mes= : Mes en formato YYYY-MM (por defecto: mes calendario actual)} {--dry-run : Generar Excel y no enviar correo}
```

Archivo: `app/Console/Commands/EnviarReporteFlujoCajaMensualSuscripcion.php`

#### `suscripciones:reportes-internos-equipo`

Reportes internos al equipo SmartPyme: vencen hoy, acceso limitado mañana, o vencen la próxima semana (viernes)

```bash
php artisan suscripciones:reportes-internos-equipo {--solo=diario : diario (A+B) o semanal (viernes)} {--dry-run : No enviar correos}
```

Archivo: `app/Console/Commands/EnviarReportesInternosSuscripcionEquipo.php`

#### `suscripciones:verificar`

Verifica y actualiza el estado de las suscripciones, períodos de prueba y envía notificaciones

```bash
php artisan suscripciones:verificar
```

Archivo: `app/Console/Commands/VerificarSuscripcion.php`

### Tipos de cambio

#### `tipos-cambio:sync-dia`

Consulta APIs de tipo de cambio y guarda rate_del_dia en pais_configuracion

```bash
php artisan tipos-cambio:sync-dia
```

Archivo: `app/Console/Commands/SyncTiposCambioDiaCommand.php`

### Ventas

#### `ventas:ajustar-iva-detalles-2026`

Calcula y rellena el IVA en detalles de venta gravados del año 2026 donde iva es cero o nulo

```bash
php artisan ventas:ajustar-iva-detalles-2026 {--dry-run : Solo mostrar qué se actualizaría, sin ejecutar} {--id_venta= : Limitar a una venta (opcional)}
```

Archivo: `app/Console/Commands/AjustarIvaDetallesVentas2026.php`

#### `ventas:corregir-impuestos-duplicados`

Elimina impuestos duplicados en venta_impuestos (mismo id_venta + id_impuesto), dejando uno con la suma de montos

```bash
php artisan ventas:corregir-impuestos-duplicados {--dry-run : Solo mostrar qué se corregiría, sin ejecutar} {--id_venta= : Corregir solo una venta (opcional)}
```

Archivo: `app/Console/Commands/CorregirImpuestosVentasDuplicados.php`

#### `ventas:eliminar-rango`

Elimina ventas y sus registros relacionados en el rango de IDs especificado

```bash
php artisan ventas:eliminar-rango {desde=1085876 : ID inicial} {hasta=1086730 : ID final}
```

Archivo: `app/Console/Commands/EliminarVentasRango.php`

#### `ventas:exportar-recuperar`

Exporta ventas/clientes/detalles perdidos a arrays PHP para importar en producción

```bash
php artisan ventas:exportar-recuperar {--fecha-inicio= : Fecha inicio (Y-m-d)} {--fecha-fin= : Fecha fin (Y-m-d)} {--id-empresa= : Filtrar solo por esta empresa (opcional)} {--ruta=datos/recuperar : Carpeta donde guardar los archivos}
```

Archivo: `app/Console/Commands/ExportarVentasPerdidasPhp.php`

#### `ventas:generar-plantillas`

Genera la plantilla Excel unificada para importación de ventas históricas

```bash
php artisan ventas:generar-plantillas
```

Archivo: `app/Console/Commands/GenerarPlantillasCommand.php`

#### `ventas:generar-recurrentes`

Genera y emite las ventas recurrentes que corresponden al día de hoy

```bash
php artisan ventas:generar-recurrentes
```

Archivo: `app/Console/Commands/GenerarVentasRecurrentes.php`

#### `ventas:importar-datos`

Importa ventas y detalles desde archivos PHP en la carpeta datos, actualizando inventario y kardex

```bash
php artisan ventas:importar-datos {--ruta-ventas=datos/ventas.php : Ruta al archivo ventas.php} {--ruta-detalles=datos/detalles_venta.php : Ruta al archivo detalles_venta.php} {--solo-insertar : Solo insertar datos, no actualizar inventario/kardex}
```

Archivo: `app/Console/Commands/ImportarVentasDatos.php`

#### `ventas:importar-recuperar`

Importa ventas perdidas desde archivo PHP exportado (ejecutar en producción)

```bash
php artisan ventas:importar-recuperar {--archivo= : Nombre del archivo (ej: ventas_perdidas_2026-02-11_2026-02-12.php)} {--ruta=datos/recuperar : Carpeta donde está el archivo} {--chunk=100 : Registros por lote para no saturar el servidor} {--delay=100 : Milisegundos de pausa entre lotes (reduce carga en prod)} {--dry-run : Solo validar, no insertar} {--solo-insertar : No actualizar inventario ni kardex}
```

Archivo: `app/Console/Commands/ImportarVentasRecuperar.php`

#### `ventas:recuperar-perdidas`

Identifica ventas y clientes perdidos en sp_nova que no están en vps

```bash
php artisan ventas:recuperar-perdidas {--fecha-inicio=2026-02-11 : Fecha inicio (Y-m-d)} {--fecha-fin=2026-02-12 : Fecha fin (Y-m-d)} {--excel : Generar archivo Excel}
```

Archivo: `app/Console/Commands/RecuperarVentasPerdidas.php`

### WhatsApp

#### `whatsapp:generate-codes`

Generar códigos únicos de WhatsApp para todas las empresas

```bash
php artisan whatsapp:generate-codes
```

Archivo: `app/Console/Commands/GenerateWhatsAppCodes.php`

### WooCommerce

#### `woocommerce:generate-keys`

Genera claves API de WooCommerce para empresas

```bash
php artisan woocommerce:generate-keys {--empresa= : ID de empresa específica (opcional)} {--force : Sobrescribir claves existentes} {--active-only : Solo generar para empresas activas}
```

Archivo: `app/Console/Commands/GenerateWooCommerceApiKeys.php`

### Empleados

#### `empleados:actualizar-estado`

Actualiza el estado de los empleados que han llegado a su fecha de baja

```bash
php artisan empleados:actualizar-estado
```

Archivo: `app/Console/Commands/ActualizarEstadoEmpleados.php`

### Paquetes

#### `paquetes:actualizar-estado`

Actualiza paquetes de estado En Bodega a Facturado, excluyendo los WR listados en wr.txt

```bash
php artisan paquetes:actualizar-estado {--ruta=datos/wr.txt : Ruta al archivo wr.txt con los WR a excluir}
```

Archivo: `app/Console/Commands/ActualizarEstadoPaquetes.php`

---

## Regenerar este documento

```bash
cd Backend
php scripts/generate-artisan-commands-doc.php
```

Salida: `Docs/ARTISAN_COMMANDS.md` (raíz del monorepo).

