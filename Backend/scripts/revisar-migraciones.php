<?php
/**
 * Revisa migraciones antes de que lleguen a producción. Lo usan el CI (migraciones nuevas del PR)
 * y el despliegue a producción (migraciones pendientes en el servidor).
 *
 *   php scripts/revisar-migraciones.php database/migrations/2026_12_01_000000_algo.php [...]
 *
 * Bloquea (sale con código 1) si el up() de una migración:
 *   - BORRA o PUEDE PERDER datos: drop de tabla/columna, truncate, delete, renombrar, ->change()
 *     (un cambio de tipo puede recortar datos) o SQL crudo con DROP/TRUNCATE/DELETE/RENAME/MODIFY.
 *     Para permitirla hay que escribir en el archivo:   // @migracion-destructiva: <motivo>
 *   - Es PESADA en una tabla grande (reconstruye la tabla o crea índices sobre millones de filas).
 *     Para permitirla:                                   // @migracion-pesada: <motivo>
 *
 * Agregar columnas o tablas nuevas NO necesita marca (en MariaDB 10.11 es instantáneo).
 * No necesita Laravel: solo lee los archivos.
 */

const TABLAS_GRANDES = [
    'ventas', 'detalles_venta', 'kardexs', 'ajustes', 'inventario', 'productos', 'venta_impuestos',
    'partida_detalles', 'clientes', 'compras', 'detalles_compra', 'producto_impuestos', 'accesos',
];

const DESTRUCTIVAS = [
    // dropIndex/dropForeign/dropUnique/dropPrimary no borran datos: no se marcan.
    '/->\s*drop(?!Foreign|Index|Unique|Primary|FullText|SpatialIndex|ConstrainedForeignId)\w*\s*\(/i' => 'borra columnas (drop...)',
    '/Schema::\s*drop\w*\s*\(/i' => 'borra una tabla (Schema::drop...)',
    '/->\s*renameColumn\s*\(/i' => 'renombra una columna',
    '/Schema::\s*rename\s*\(/i' => 'renombra una tabla',
    '/->\s*truncate\s*\(/i' => 'vacía una tabla (truncate)',
    '/->\s*(delete|forceDelete)\s*\(/i' => 'borra filas (delete)',
    '/DB::\s*delete\s*\(/i' => 'borra filas (DB::delete)',
    '/->\s*change\s*\(\s*\)/i' => 'cambia el tipo de una columna (->change(), puede recortar datos)',
    '/\b(DROP\s+(TABLE|COLUMN|DATABASE)|TRUNCATE\b|DELETE\s+FROM|RENAME\s+(TABLE|COLUMN)|MODIFY\s+(COLUMN\s+)?`?\w|CHANGE\s+(COLUMN\s+)?`?\w)/i' => 'SQL crudo que borra o modifica estructura/datos',
];

// Operaciones que reconstruyen la tabla o recorren todas sus filas.
const PESADAS = [
    '/->\s*(index|unique|fullText|spatialIndex|primary|foreign)\s*\(/i' => 'crea un índice o llave foránea',
    '/->\s*change\s*\(\s*\)/i' => 'cambia el tipo de una columna (reconstruye la tabla)',
    '/\bALTER\s+TABLE\b/i' => 'ALTER TABLE en SQL crudo',
    '/\bUPDATE\s+`?\w+`?\s+SET\b/i' => 'UPDATE masivo en SQL crudo',
];

function cuerpoUp(string $codigo): ?string
{
    if (!preg_match('/function\s+up\s*\([^)]*\)[^{]*\{/i', $codigo, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $inicio = $m[0][1] + strlen($m[0][0]);
    $nivel = 1;
    for ($i = $inicio, $n = strlen($codigo); $i < $n; $i++) {
        if ($codigo[$i] === '{') {
            $nivel++;
        } elseif ($codigo[$i] === '}' && --$nivel === 0) {
            return substr($codigo, $inicio, $i - $inicio);
        }
    }
    return substr($codigo, $inicio);
}

function sinComentarios(string $php): string
{
    $salida = '';
    foreach (token_get_all("<?php\n" . $php) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT || $t[0] === T_OPEN_TAG) {
                $salida .= str_repeat("\n", substr_count($t[1], "\n"));
                continue;
            }
            $salida .= $t[1];
        } else {
            $salida .= $t;
        }
    }
    return $salida;
}

function tablasTocadas(string $up): array
{
    preg_match_all('/Schema::\s*(?:table|create)\s*\(\s*[\'"](\w+)[\'"]/i', $up, $a);
    preg_match_all('/\b(?:ALTER\s+TABLE|UPDATE)\s+`?(\w+)`?/i', $up, $b);
    preg_match_all('/DB::\s*table\s*\(\s*[\'"](\w+)[\'"]/i', $up, $c);
    return array_values(array_unique(array_map('strtolower', array_merge($a[1], $b[1], $c[1]))));
}

$archivos = array_slice($argv, 1);
if (!$archivos) {
    echo "Sin migraciones que revisar.\n";
    exit(0);
}

$bloqueadas = 0;
foreach ($archivos as $archivo) {
    if (!is_file($archivo)) {
        continue; // borrada en el PR: no corre
    }
    $codigo = file_get_contents($archivo);
    $nombre = basename($archivo);
    $up = cuerpoUp($codigo);
    if ($up === null) {
        echo "ATENCIÓN {$nombre}: no se encontró up(), se revisa el archivo completo.\n";
        $up = $codigo;
    }
    $up = sinComentarios($up);
    $marcaDestructiva = preg_match('/@migracion-destructiva:\s*\S/', $codigo) === 1;
    $marcaPesada = preg_match('/@migracion-pesada:\s*\S/', $codigo) === 1;

    $destructivas = [];
    foreach (DESTRUCTIVAS as $patron => $motivo) {
        if (preg_match($patron, $up)) {
            $destructivas[] = $motivo;
        }
    }
    $grandes = array_values(array_intersect(tablasTocadas($up), TABLAS_GRANDES));
    $pesadas = [];
    if ($grandes) {
        foreach (PESADAS as $patron => $motivo) {
            if (preg_match($patron, $up)) {
                $pesadas[] = $motivo . ' en ' . implode(', ', $grandes);
            }
        }
    }

    foreach ([['destructiva', $destructivas, $marcaDestructiva], ['pesada', $pesadas, $marcaPesada]] as [$tipo, $hallazgos, $marcada]) {
        if (!$hallazgos) {
            continue;
        }
        $lista = implode('; ', array_unique($hallazgos));
        if ($marcada) {
            echo "PERMITIDA ({$tipo}, marcada) {$nombre}: {$lista}\n";
        } else {
            $bloqueadas++;
            echo "BLOQUEADA ({$tipo}) {$nombre}: {$lista}\n";
            echo "    Si es intencional, agregue en el archivo:  // @migracion-{$tipo}: <motivo>\n";
        }
    }
}

if ($bloqueadas) {
    echo "\n{$bloqueadas} problema(s). Ver Backend/scripts/revisar-migraciones.php y ops/prod/README.md.\n";
    exit(1);
}
echo 'Migraciones revisadas: ' . count($archivos) . ", sin problemas.\n";
