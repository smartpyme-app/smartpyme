<?php

/**
 * php tests/Unit/MailIngest/ingest_phase2_check.php
 */

require_once __DIR__ . '/../../../app/Services/MailIngest/IngestRecipientResolver.php';
require_once __DIR__ . '/../../../app/Services/MailIngest/InboundMimeParser.php';

use App\Services\MailIngest\InboundMimeParser;
use App\Services\MailIngest\IngestRecipientResolver;

$check = static function ($ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

$resolver = new IngestRecipientResolver();
$check($resolver->tenantDomain() === 'ingest.smartpyme.site', 'tenant domain');

$token = $resolver->resolveToken([
    ['source' => 'header.To', 'value' => 'contador@gmail.com'],
    ['source' => 'header.Delivered-To', 'value' => 'abc123@ingest.smartpyme.site'],
    ['source' => 'argv', 'value' => 'other@example.com'],
]);
$check($token === 'abc123', 'must prefer Delivered-To on ingest domain, not To:. got ' . var_export($token, true));

$ignoredTo = $resolver->resolveToken([
    ['source' => 'header.To', 'value' => 'prueba123@ingest.smartpyme.site'],
]);
$check($ignoredTo === null, 'To: must never resolve the tenant');

$fromArgv = $resolver->resolveToken([
    ['source' => 'argv', 'value' => 'via-argv@ingest.smartpyme.site'],
    ['source' => 'header.Delivered-To', 'value' => 'other@ingest.smartpyme.site'],
]);
$check($fromArgv === 'via-argv', 'argv on tenant domain wins over later headers');

$json = '{"identificacion":{"codigoGeneracion":"u-1","tipoDte":"01"}}';
$boundary = 'bnd42';
$eml = "From: proveedor@example.com\r\n"
    . "To: contador@gmail.com\r\n"
    . "Delivered-To: abc123@ingest.smartpyme.site\r\n"
    . "Message-ID: <msg-1@test>\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n"
    . "\r\n"
    . "--{$boundary}\r\n"
    . "Content-Type: text/plain\r\n"
    . "\r\n"
    . "hola\r\n"
    . "--{$boundary}\r\n"
    . "Content-Type: application/json; name=\"dte.json\"\r\n"
    . "Content-Disposition: attachment; filename=\"dte.json\"\r\n"
    . "Content-Transfer-Encoding: base64\r\n"
    . "\r\n"
    . chunk_split(base64_encode($json), 76, "\r\n")
    . "--{$boundary}--\r\n";

$parser = new InboundMimeParser();
$attachments = $parser->attachments($eml);
$check(count($attachments) === 1, 'expected 1 attachment, got ' . count($attachments));
$check($attachments[0]['filename'] === 'dte.json', 'filename');
$check($attachments[0]['content'] === $json, 'decoded json');
$check($parser->messageId($eml) === '<msg-1@test>', 'message-id');

fwrite(STDOUT, "ingest_phase2_check: ok\n");
