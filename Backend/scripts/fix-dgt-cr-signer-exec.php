<?php

/**
 * dgt-cr-signer llama exec() sin \ dentro del namespace DazzaDev\DgtCrSigner → PHP busca DazzaDev\DgtCrSigner\exec.
 * El parche de Composer a veces no reaplica; este script es idempotente y se ejecuta tras composer install/update.
 */
$path = dirname(__DIR__).'/vendor/dazza-dev/dgt-cr-signer/src/Certificate.php';
if (! is_file($path)) {
    exit(0);
}

$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "fix-dgt-cr-signer-exec: no se pudo leer Certificate.php\n");
    exit(0);
}

$needle = "        exec(\$command.' 2>&1', \$output, \$returnCode);";
$fixed = "        \\exec(\$command.' 2>&1', \$output, \$returnCode);";

if (str_contains($contents, $fixed)) {
    exit(0);
}

if (! str_contains($contents, $needle)) {
    exit(0);
}

$count = 0;
$new = str_replace($needle, $fixed, $contents, $count);
if ($count > 0) {
    file_put_contents($path, $new);
    fwrite(STDERR, "fix-dgt-cr-signer-exec: aplicado en dgt-cr-signer (exec → \\exec)\n");
}
