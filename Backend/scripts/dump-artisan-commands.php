<?php

$root = dirname(__DIR__) . '/app/Console/Commands';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
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
    $rel = str_replace(dirname(__DIR__) . '/app/Console/Commands/', '', $f->getPathname());
    $cmds[$name] = ['sig' => $sig, 'desc' => $desc, 'file' => $rel];
}

ksort($cmds);
echo json_encode($cmds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
