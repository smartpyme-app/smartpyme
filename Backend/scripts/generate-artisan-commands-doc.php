<?php

/**
 * Genera Docs/ARTISAN_COMMANDS.md desde app/Console/Commands.
 * Uso: php scripts/generate-artisan-commands-doc.php
 */

$backendRoot = dirname(__DIR__);
$commandsRoot = $backendRoot . '/app/Console/Commands';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($commandsRoot));
$cmds = [];

foreach ($files as $f) {
    if (!$f->isFile() || !str_ends_with($f->getFilename(), '.php')) {
        continue;
    }
    $src = file_get_contents($f->getPathname());
    if (!preg_match("/protected \\\$signature\s*=\s*'([^']*(?:'\s*\.\s*'[^']*)*)'\s*;/s", $src, $m)
        && !preg_match('/protected \$signature\s*=\s*"([^"]*)"\s*;/s', $src, $m)) {
        continue;
    }
    $sig = preg_replace("/\s+/", ' ', trim($m[1]));
    $desc = '';
    if (preg_match("/protected \\\$description\s*=\s*'((?:\\\\'|[^'])*)'\s*;/s", $src, $d)) {
        $desc = preg_replace("/\s+/", ' ', trim(stripslashes($d[1])));
    }
    $name = preg_match('/^([^\s{]+)/', $sig, $n) ? $n[1] : $sig;
    $rel = str_replace($commandsRoot . '/', '', $f->getPathname());
    $prefix = str_contains($name, ':') ? explode(':', $name, 2)[0] : 'otros';
    $cmds[$name] = ['sig' => $sig, 'desc' => $desc, 'file' => $rel, 'prefix' => $prefix];
}

ksort($cmds);

$categoryLabels = [
    'activos' => 'Activos fijos',
    'attributes' => 'Atributos de producto',
    'auditoria' => 'Auditoría',
    'authorizations' => 'Autorizaciones',
    'bonos' => 'Bonos comerciales',
    'certificado' => 'Certificados / SFTP',
    'cliente360' => 'Cliente 360',
    'clientes' => 'Clientes',
    'compras' => 'Compras',
    'contabilidad' => 'Contabilidad',
    'dte' => 'DTE / facturación electrónica',
    'empresa-config' => 'Configuración de empresa',
    'export' => 'Exportaciones catálogo',
    'facturas' => 'Facturación SmartPyme (suscripciones)',
    'fidelizacion' => 'Fidelización',
    'generate' => 'Generación (notificaciones, cortes)',
    'ia' => 'Inteligencia artificial',
    'imagenes' => 'Imágenes productos',
    'importar' => 'Importación catálogos geo',
    'inventario' => 'Inventario / análisis',
    'kardex' => 'Kardex',
    'metricas' => 'Métricas',
    'migrate' => 'Migraciones custom',
    'prestamos' => 'Préstamos',
    'productos' => 'Productos',
    'reporte' => 'Reportes puntuales',
    'reportes' => 'Reportes automáticos',
    'restaurante' => 'Restaurante',
    'shopify' => 'Shopify',
    'subscription' => 'Suscripciones (alertas UI)',
    'suscripciones' => 'Suscripciones SmartPyme',
    'tipos-cambio' => 'Tipos de cambio',
    'trabajos' => 'Cola de trabajos',
    'ventas' => 'Ventas',
    'whatsapp' => 'WhatsApp',
    'woocommerce' => 'WooCommerce',
    'otros' => 'Otros',
];

$byCategory = [];
foreach ($cmds as $name => $meta) {
    $cat = $meta['prefix'];
    $byCategory[$cat][] = array_merge(['name' => $name], $meta);
}

uksort($byCategory, fn ($a, $b) => ($categoryLabels[$a] ?? $a) <=> ($categoryLabels[$b] ?? $b));

$scheduled = [
    ['generate:notificaciones', 'Diario', 'Notificaciones del sistema'],
    ['reportes:enviar', 'Cada 5 min', 'Encola reportes automáticos configurados'],
    ['auditoria:purge', 'Día 1 de cada mes, 04:00', 'Purga auditoría antigua'],
    ['reportes:limpiar-exportaciones', 'Diario 03:30', 'Limpia exportaciones de reportes'],
    ['metricas:empresas', 'Diario 03:00', 'Métricas por empresa'],
    ['metricas:sucursales', 'Diario 04:00', 'Métricas por sucursal'],
    ['metricas:empresas --actualizar-historico', 'Día 1, 02:00', 'Histórico mensual empresas'],
    ['metricas:sucursales --actualizar-historico', 'Día 1, 02:30', 'Histórico mensual sucursales'],
    ['empleados:actualizar-estado', 'Diario 00:01', 'Estado de empleados'],
    ['suscripciones:verificar', 'Diario 01:00', 'Estado de suscripciones y trials'],
    ['facturas:generar-suscripciones', 'Día 1, 08:00', 'DTE de suscripciones por transferencia'],
    ['ventas:generar-recurrentes', 'Diario 06:00 (America/El_Salvador)', 'Ventas recurrentes del día'],
    ['suscripciones:enviar-recordatorios-correo', 'Diario 08:00', 'Recordatorios de suscripción'],
    ['prestamos:recordatorios-vencimiento', 'Diario 07:00', 'Cuotas de préstamo por vencer'],
    ['suscripciones:reportes-internos-equipo --solo=diario', 'Diario 08:00', 'Reporte interno equipo (diario)'],
    ['suscripciones:reportes-internos-equipo --solo=semanal', 'Viernes 08:00', 'Reporte interno equipo (semanal)'],
    ['suscripciones:reporte-flujo-caja-mensual', 'Día 1, 08:15', 'Flujo de caja suscripciones'],
    ['suscripciones:reporte-bajas-mensual', 'Día 1, 08:30', 'Bajas de suscripción'],
    ['reporte:ventas-por-categoria-sucursal', 'Diario 08:00', 'Ventas por categoría y sucursal'],
    ['cliente360:actualizar-agregados --masivo', 'Cada minuto', 'Agregados Cliente 360'],
    ['fidelizacion:procesar-expiracion-puntos --sync', 'Diario 02:00', 'Expiración de puntos'],
    ['bonos:evaluar', 'Diario 02:30', 'Evaluación de bonos vendedores'],
    ['activos:depreciacion-mensual', 'Diario 05:00', 'Depreciación activos fijos'],
    ['dte:migrate-to-s3', 'Diario 02:45 (si config dte.schedule_enabled)', 'Migración DTE a S3'],
    ['dte:sync-accounts --dias=30', 'Cada hora', 'Sync buzones DTE'],
    ['dte:ingest-spool --limit=50', 'Cada minuto', 'Procesa spool de correos DTE'],
    ['dte:ingest-purge --days=7', 'Diario 03:20', 'Purga spool DTE'],
    ['tipos-cambio:sync-dia', 'Diario 06:00 (America/Costa_Rica)', 'Tipo de cambio del día'],
];

$danger = [
    'fidelizacion:reset-empresa',
    'fidelizacion:procesar-ventas-existentes',
    'ventas:eliminar-rango',
    'compras:eliminar-rango',
    'clientes:fusionar-duplicados',
    'dte:migrate-to-s3',
    'imagenes:migrate-to-s3',
    'compras:sync-productos',
];

$out = [];
$out[] = '# Comandos Artisan — SmartPyme Backend';
$out[] = '';
$out[] = 'Documentación de referencia para desarrollo y operaciones. Generada desde el código en `Backend/app/Console/Commands`.';
$out[] = '';
$out[] = '**Total de comandos propios:** ' . count($cmds) . ' (excluye comandos nativos de Laravel).';
$out[] = '';
$out[] = '### Publicar en Confluence';
$out[] = '';
$out[] = '1. Crear una página en el espacio del equipo.';
$out[] = '2. Usar el macro **Markdown** (o *Insert markup* → Markdown) y pegar el contenido de este archivo.';
$out[] = '3. Alternativa: adjuntar el `.md` o convertir con una herramienta de importación Markdown → Confluence.';
$out[] = '4. Tras agregar comandos nuevos en código, regenerar con `php scripts/generate-artisan-commands-doc.php`.';
$out[] = '';
$out[] = '---';
$out[] = '';
$out[] = '## Cómo ejecutar';
$out[] = '';
$out[] = 'Desde la raíz del backend (mismo entorno y `.env` que la API que consultas):';
$out[] = '';
$out[] = '```bash';
$out[] = 'cd Backend';
$out[] = 'php artisan list                    # listado completo';
$out[] = 'php artisan <comando> --help        # ayuda de un comando';
$out[] = 'php artisan fidelizacion:reset-empresa 756 --dry-run';
$out[] = '```';
$out[] = '';
$out[] = 'En **producción**, ejecutar dentro del contenedor/servidor donde corre la API (misma base de datos que usa el frontend).';
$out[] = '';
$out[] = 'Convenciones frecuentes:';
$out[] = '';
$out[] = '| Opción | Significado |';
$out[] = '|--------|-------------|';
$out[] = '| `--dry-run` | Simula o muestra conteos sin escribir |';
$out[] = '| `--force` | Omite confirmación interactiva |';
$out[] = '| `--empresa=` | Filtra por ID de empresa |';
$out[] = '';
$out[] = '---';
$out[] = '';
$out[] = '## Comandos programados (cron / scheduler)';
$out[] = '';
$out[] = 'Definidos en `app/Console/Kernel.php`. En producción el scheduler de Laravel debe estar activo (`* * * * * php artisan schedule:run`).';
$out[] = '';
$out[] = '| Comando | Frecuencia | Notas |';
$out[] = '|---------|------------|-------|';
foreach ($scheduled as [$cmd, $freq, $note]) {
    $out[] = '| `' . $cmd . '` | ' . $freq . ' | ' . $note . ' |';
}
$out[] = '';
$out[] = '---';
$out[] = '';
$out[] = '## Comandos con impacto destructivo o irreversible';
$out[] = '';
$out[] = 'Usar solo con `--dry-run` primero, backup de BD y acuerdo del equipo:';
$out[] = '';
foreach ($danger as $d) {
    if (isset($cmds[$d])) {
        $out[] = '- **`' . $d . '`** — ' . $cmds[$d]['desc'];
    }
}
$out[] = '';
$out[] = '---';
$out[] = '';
$out[] = '## Catálogo por módulo';
$out[] = '';

foreach ($byCategory as $prefix => $items) {
    $label = $categoryLabels[$prefix] ?? ucfirst($prefix);
    $out[] = '### ' . $label;
    $out[] = '';
    foreach ($items as $item) {
        $out[] = '#### `' . $item['name'] . '`';
        $out[] = '';
        $out[] = $item['desc'] !== '' ? $item['desc'] : '_Sin descripción en código._';
        $out[] = '';
        $out[] = '```bash';
        $out[] = 'php artisan ' . $item['sig'];
        $out[] = '```';
        $out[] = '';
        $out[] = 'Archivo: `app/Console/Commands/' . $item['file'] . '`';
        $out[] = '';
    }
}

$out[] = '---';
$out[] = '';
$out[] = '## Regenerar este documento';
$out[] = '';
$out[] = '```bash';
$out[] = 'cd Backend';
$out[] = 'php scripts/generate-artisan-commands-doc.php';
$out[] = '```';
$out[] = '';
$out[] = 'Salida: `Docs/ARTISAN_COMMANDS.md` (raíz del monorepo).';
$out[] = '';

$dest = dirname($backendRoot) . '/Docs/ARTISAN_COMMANDS.md';
file_put_contents($dest, implode("\n", $out) . "\n");
fwrite(STDERR, "Written {$dest} (" . count($cmds) . " commands)\n");
