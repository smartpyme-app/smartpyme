<?php

/**
 * Runnable check for dte-ingest-pipe.php. No PHPUnit, no assert().
 * php tests/Unit/MailIngest/dte_ingest_pipe_check.php
 */

require_once __DIR__ . '/../../../bin/dte-ingest-pipe.php';

$spool = sys_get_temp_dir() . '/dte-ingest-test-' . uniqid();
putenv('DTE_INGEST_SPOOL=' . $spool);
putenv('DTE_INGEST_MAX_BYTES=1024');

$check = static function ($ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

$rmTree = static function (string $dir) use (&$rmTree): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        is_dir($path) ? $rmTree($path) : unlink($path);
    }
    rmdir($dir);
};

$incomingEmls = static function () use ($spool): array {
    return glob($spool . '/incoming/*.eml') ?: [];
};

$failedMetas = static function () use ($spool): array {
    return glob($spool . '/failed/*.meta.json') ?: [];
};

try {
    $check(DTE_INGEST_TENANT_DOMAIN === 'ingest.smartpyme.site', 'tenant domain constant drifted');
    $check(DTE_INGEST_DEFAULT_MAX_BYTES === 15728640, 'default max must stay 15 MB');

    $raw = "Delivered-To: token-a@ingest.example\r\n"
        . "X-Original-To: token-b@ingest.example\r\n"
        . "Envelope-To: token-c@ingest.example\r\n"
        . "To: Contador <contador@gmail.com>\r\n"
        . "\r\nbody";

    $candidates = dte_ingest_collect_recipients(
        ['dte-ingest-pipe.php', 'token-argv@ingest.example'],
        [
            'RECIPIENT' => 'token-env@ingest.example',
            'LOCAL_PART' => 'token-local',
            'DOMAIN' => 'ingest.example',
        ],
        $raw
    );
    $values = array_column($candidates, 'value');
    $check(in_array('token-argv@ingest.example', $values, true), 'missing argv candidate');
    $check(in_array('token-env@ingest.example', $values, true), 'missing RECIPIENT candidate');
    $check(in_array('token-local@ingest.example', $values, true), 'missing LOCAL_PART+DOMAIN candidate');
    $check(in_array('token-a@ingest.example', $values, true), 'missing Delivered-To candidate');
    $check(in_array('token-b@ingest.example', $values, true), 'missing X-Original-To candidate');
    $check(in_array('token-c@ingest.example', $values, true), 'missing Envelope-To candidate');
    $check(in_array('contador@gmail.com', $values, true), 'missing To candidate (collected, not resolved)');
    $check(count($values) === count(array_unique($values)), 'duplicate candidates');
    $check(isset($candidates[0]['source']), 'candidate source missing');

    $eml = "Message-ID: <abc@test>\r\nTo: nobody@example.com\r\n\r\nhello";
    $under = fopen('php://temp', 'w+');
    fwrite($under, $eml);
    rewind($under);
    $readUnder = dte_ingest_read_limited($under);
    fclose($under);
    $check($readUnder['too_large'] === false, 'small message marked too_large');
    $check($readUnder['raw'] === $eml, 'small message body lost');
    $check($readUnder['bytes'] === strlen($eml), 'small message byte count');

    $written = dte_ingest_write_spool($readUnder['raw'], [
        ['source' => 'argv', 'value' => 'tok@ingest.example'],
    ], $readUnder['too_large'], $readUnder['bytes']);
    $check(is_file((string) $written['eml']), 'under-limit must create .eml');
    $check(strpos((string) $written['eml'], '/incoming/') !== false, 'under-limit eml not in incoming');
    $check(file_get_contents($written['eml']) === $eml, 'eml content mismatch');
    $check(!is_file($written['eml'] . '.part'), 'leftover eml.part after success');
    $meta = json_decode(file_get_contents($written['meta']), true);
    $check($meta['recipient_status'] === 'unresolved', 'pipe must not resolve tenant');
    $check($meta['reason'] === null, 'under-limit reason must be null');

    $incomingBefore = $incomingEmls();
    $overStream = fopen('php://temp', 'w+');
    fwrite($overStream, str_repeat('x', 2048));
    rewind($overStream);
    $readOver = dte_ingest_read_limited($overStream);
    fclose($overStream);
    $check($readOver['too_large'] === true, 'over-limit read must set too_large');
    $check($readOver['raw'] === null, 'over-limit read must drop body from memory');
    $check($readOver['bytes'] > 1024, 'over-limit must count past max');

    $oversized = dte_ingest_write_spool($readOver['raw'], [], $readOver['too_large'], $readOver['bytes']);
    $check($oversized['eml'] === null, 'over-limit must not create .eml');
    $check(is_file($oversized['meta']), 'over-limit must write failed metadata');
    $check(strpos($oversized['meta'], '/failed/') !== false, 'over-limit meta not in failed/');
    $failedMeta = json_decode(file_get_contents($oversized['meta']), true);
    $check($failedMeta['reason'] === 'too_large', 'reason must be too_large');
    $check($incomingEmls() === $incomingBefore, 'over-limit must not add incoming .eml');
    $check($failedMetas() !== [], 'failed/ metadata missing');
    $check(glob($spool . '/incoming/*.part') === false || glob($spool . '/incoming/*.part') === [], 'partial files left in incoming');

    $runStream = fopen('php://temp', 'w+');
    fwrite($runStream, str_repeat('y', 2048));
    rewind($runStream);
    $exit = dte_ingest_run(['dte-ingest-pipe.php'], [], $runStream);
    fclose($runStream);
    $check($exit === 0, 'over-limit pipe must exit 0');

    $pipeBin = realpath(__DIR__ . '/../../../bin/dte-ingest-pipe.php');
    $check($pipeBin !== false, 'pipe binary missing');
    $cmd = escapeshellarg(PHP_BINARY) . ' -q -d display_errors=0 ' . escapeshellarg($pipeBin);
    $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
    $proc = proc_open($cmd, $desc, $pipes, null, ['DTE_INGEST_SPOOL' => $spool, 'DTE_INGEST_MAX_BYTES' => '1024']);
    $check(is_resource($proc), 'could not spawn pipe');
    fwrite($pipes[0], "Delivered-To: cli@ingest.smartpyme.site\r\n\r\nok\r\n");
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($proc);
    $check($status === 0, 'cli pipe exit must be 0, got ' . $status);
    $check($stdout === '', 'cli pipe must write nothing to stdout, got: ' . $stdout);
    $check($stderr === '', 'cli pipe must write nothing to stderr, got: ' . $stderr);

    fwrite(STDOUT, "dte_ingest_pipe_check: ok\n");
} finally {
    putenv('DTE_INGEST_SPOOL');
    putenv('DTE_INGEST_MAX_BYTES');
    $rmTree($spool);
}
