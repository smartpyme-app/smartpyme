#!/usr/bin/env php
<?php

/**
 * cPanel/Exim pipe: STDIN → spool. No Laravel, no DB, no Redis.
 *
 * Production must invoke bin/dte-ingest-pipe.sh (or `php -q this-file`).
 * Piping this .php directly makes cPanel use PHP CGI, which prints
 * "Content-type: text/html" and Exim bounces the message.
 *
 * Recipient candidates are collected only. This process never resolves empresa.
 *
 * Later consumer (not this file) may accept a token ONLY when the address
 * domain is DTE_INGEST_TENANT_DOMAIN. header.To is never the primary tenant
 * source — Gmail forwards keep the original To: and put the ingest address
 * on the envelope / Delivered-To.
 */

const DTE_INGEST_TENANT_DOMAIN = 'ingest.smartpyme.site';
const DTE_INGEST_DEFAULT_MAX_BYTES = 15728640; // 15 MB
const DTE_INGEST_EX_OK = 0;
const DTE_INGEST_READ_CHUNK = 65536;

function dte_ingest_spool_root(): string
{
    $env = getenv('DTE_INGEST_SPOOL');
    if (is_string($env) && $env !== '') {
        return rtrim($env, '/');
    }

    return dirname(__DIR__) . '/storage/app/mail-ingest';
}

function dte_ingest_max_bytes(): int
{
    $env = getenv('DTE_INGEST_MAX_BYTES');
    if (is_string($env) && ctype_digit($env)) {
        return (int) $env;
    }

    return DTE_INGEST_DEFAULT_MAX_BYTES;
}

function dte_ingest_ensure_dirs(string $root): void
{
    foreach (['incoming', 'processing', 'failed'] as $dir) {
        $path = $root . '/' . $dir;
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create ' . $path);
        }
    }
}

function dte_ingest_extract_emails(string $text): array
{
    if ($text === '') {
        return [];
    }
    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

    return array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
}

function dte_ingest_header_block(string $raw): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
    $split = preg_split("/\n\n/", $normalized, 2);

    return $split[0] ?? '';
}

function dte_ingest_header_values(string $headers, string $name): array
{
    // ponytail: no RFC5322 unfolding. Upgrade if Exim folds Delivered-To onto the next line.
    $values = [];
    foreach (explode("\n", $headers) as $line) {
        if (stripos($line, $name . ':') === 0) {
            $values[] = trim(substr($line, strlen($name) + 1));
        }
    }

    return $values;
}

/**
 * @param  list<string>  $argv
 * @param  array<string, mixed>  $env
 * @return list<array{source: string, value: string}>
 */
function dte_ingest_collect_recipients(array $argv, array $env, string $raw): array
{
    $candidates = [];
    $seen = [];

    $add = static function (string $source, string $value) use (&$candidates, &$seen): void {
        $email = strtolower(trim($value));
        if ($email === '' || isset($seen[$source . "\0" . $email])) {
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $seen[$source . "\0" . $email] = true;
        $candidates[] = ['source' => $source, 'value' => $email];
    };

    if (isset($argv[1])) {
        foreach (dte_ingest_extract_emails((string) $argv[1]) as $email) {
            $add('argv', $email);
        }
    }

    if (!empty($env['RECIPIENT'])) {
        foreach (dte_ingest_extract_emails((string) $env['RECIPIENT']) as $email) {
            $add('env.RECIPIENT', $email);
        }
    }

    $local = isset($env['LOCAL_PART']) ? trim((string) $env['LOCAL_PART']) : '';
    $domain = isset($env['DOMAIN']) ? trim((string) $env['DOMAIN']) : '';
    if ($local !== '' && $domain !== '') {
        $add('env.LOCAL_PART+DOMAIN', strtolower($local . '@' . $domain));
    }

    $headers = dte_ingest_header_block($raw);
    foreach (['Delivered-To', 'X-Original-To', 'Envelope-To', 'X-Envelope-To'] as $header) {
        foreach (dte_ingest_header_values($headers, $header) as $value) {
            foreach (dte_ingest_extract_emails($value) as $email) {
                $add('header.' . $header, $email);
            }
        }
    }

    // Collected last and never used as primary tenant by the future consumer.
    foreach (dte_ingest_header_values($headers, 'To') as $value) {
        foreach (dte_ingest_extract_emails($value) as $email) {
            $add('header.To', $email);
        }
    }

    return $candidates;
}

/**
 * Read STDIN in chunks. Stop keeping the body once DTE_INGEST_MAX_BYTES is exceeded.
 * Drain the rest so Exim does not see a broken pipe.
 *
 * @param  resource  $stdin
 * @return array{raw: ?string, bytes: int, too_large: bool}
 */
function dte_ingest_read_limited($stdin, ?int $max = null): array
{
    $max = $max ?? dte_ingest_max_bytes();
    $raw = '';
    $bytes = 0;
    $tooLarge = false;

    while (!feof($stdin)) {
        $chunk = fread($stdin, DTE_INGEST_READ_CHUNK);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $len = strlen($chunk);
        if ($tooLarge || ($bytes + $len) > $max) {
            $tooLarge = true;
            $raw = '';
            $bytes += $len;
            continue;
        }
        $raw .= $chunk;
        $bytes += $len;
    }

    return [
        'raw' => $tooLarge ? null : $raw,
        'bytes' => $bytes,
        'too_large' => $tooLarge,
    ];
}

/**
 * @param  list<array{source: string, value: string}>  $candidates
 * @return array{eml: ?string, meta: string}
 */
function dte_ingest_write_spool(?string $raw, array $candidates, bool $tooLarge = false, ?int $bytes = null): array
{
    $root = dte_ingest_spool_root();
    dte_ingest_ensure_dirs($root);

    $id = date('YmdHis') . '-' . bin2hex(random_bytes(8));
    $max = dte_ingest_max_bytes();
    $size = $bytes ?? strlen((string) $raw);
    $tooLarge = $tooLarge || $size > $max;

    $meta = [
        'id' => $id,
        'bytes' => $size,
        'max_bytes' => $max,
        'received_at' => gmdate('c'),
        'recipient_status' => 'unresolved',
        'tenant_domain' => DTE_INGEST_TENANT_DOMAIN,
        'recipient_candidates' => $candidates,
        'reason' => $tooLarge ? 'too_large' : null,
    ];

    if ($tooLarge || $raw === null) {
        $metaPath = $root . '/failed/' . $id . '.meta.json';
        if (file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_SLASHES) . "\n") === false) {
            throw new RuntimeException('Cannot write ' . $metaPath);
        }
        @chmod($metaPath, 0600);

        return ['eml' => null, 'meta' => $metaPath];
    }

    $emlFinal = $root . '/incoming/' . $id . '.eml';
    $metaFinal = $root . '/incoming/' . $id . '.meta.json';
    $emlPart = $emlFinal . '.part';
    $metaPart = $metaFinal . '.part';

    if (file_put_contents($emlPart, $raw) === false) {
        throw new RuntimeException('Cannot write ' . $emlPart);
    }
    if (file_put_contents($metaPart, json_encode($meta, JSON_UNESCAPED_SLASHES) . "\n") === false) {
        @unlink($emlPart);
        throw new RuntimeException('Cannot write ' . $metaPart);
    }
    @chmod($emlPart, 0600);
    @chmod($metaPart, 0600);

    if (!rename($metaPart, $metaFinal)) {
        @unlink($emlPart);
        @unlink($metaPart);
        throw new RuntimeException('Cannot rename meta');
    }
    if (!rename($emlPart, $emlFinal)) {
        @unlink($emlPart);
        @unlink($metaFinal);
        throw new RuntimeException('Cannot rename eml');
    }

    return ['eml' => $emlFinal, 'meta' => $metaFinal];
}

function dte_ingest_log(string $line): void
{
    $file = dirname(__DIR__) . '/storage/logs/dte-ingest-pipe.log';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        return;
    }
    @file_put_contents($file, gmdate('c') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

function dte_ingest_run(array $argv, array $env, $stdin): int
{
    try {
        $read = dte_ingest_read_limited($stdin);
        $raw = $read['too_large'] ? '' : (string) $read['raw'];
        $candidates = $read['too_large']
            ? []
            : dte_ingest_collect_recipients($argv, $env, $raw);
        $written = dte_ingest_write_spool(
            $read['too_large'] ? null : $raw,
            $candidates,
            $read['too_large'],
            $read['bytes']
        );
        dte_ingest_log(sprintf(
            'ok bytes=%d eml=%s candidates=%d too_large=%d',
            $read['bytes'],
            $written['eml'] ?? 'none',
            count($candidates),
            $read['too_large'] ? 1 : 0
        ));
    } catch (Throwable $e) {
        dte_ingest_log('error ' . $e->getMessage());
    }

    return DTE_INGEST_EX_OK;
}

$script = $_SERVER['SCRIPT_FILENAME'] ?? ($argv[0] ?? '');
$invokedDirectly = $script !== '' && @realpath((string) $script) === @realpath(__FILE__);
if ($invokedDirectly) {
    // CGI/cPanel: any stdout (including auto Content-type) makes Exim bounce.
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    if (function_exists('header_remove')) {
        @header_remove();
    }
    ob_start();
    $code = dte_ingest_run($argv, $_SERVER, STDIN);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    exit($code);
}
