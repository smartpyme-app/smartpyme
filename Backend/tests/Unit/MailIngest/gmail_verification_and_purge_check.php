<?php

require_once __DIR__ . '/../../../app/Services/MailIngest/GmailForwardingVerificationDetector.php';
require_once __DIR__ . '/../../../app/Services/MailIngest/IngestSpoolPurge.php';

use App\Services\MailIngest\GmailForwardingVerificationDetector;
use App\Services\MailIngest\IngestSpoolPurge;

$check = static function ($ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

$detector = new GmailForwardingVerificationDetector();

$gmail = "From: forwarding-noreply@google.com\r\n"
    . "Subject: (Gmail Forwarding Confirmation - Receive Mail from a@gmail.com)\r\n"
    . "\r\n"
    . "Confirmation code: 847291036\r\n"
    . "https://mail.google.com/mail/vf-abc123\r\n";

$found = $detector->detect($gmail);
$check($found !== null, 'gmail confirmation must be detected');
$check($found['code'] === '847291036', 'code extracted, got ' . ($found['code'] ?? ''));
$check(str_contains((string) $found['link'], 'mail.google.com'), 'link extracted');

$normal = "From: proveedor@example.com\r\nSubject: Factura\r\n\r\nJSON adjunto\r\n";
$check($detector->detect($normal) === null, 'normal DTE mail must not look like verification');

$toOnly = "From: contador@gmail.com\r\nSubject: hola\r\nTo: x@ingest.smartpyme.site\r\n\r\nno\r\n";
$check($detector->detect($toOnly) === null, 'random gmail user mail is not verification');

// Production format (2026): no numeric code; confirm link is mail-settings.google.com/mail/vf-
$prod = "From: forwarding-noreply@google.com\r\n"
    . "Subject: =?UTF-8?Q?(Gmail_Forwarding_Confirmation_-_Receive_Mail_from_a@gmail.com)?=\r\n"
    . "\r\n"
    . "a@gmail.com has requested to automatically forward mail to your email\r\n"
    . "address token@ingest.smartpyme.site.\r\n\r\n"
    . "please click the link below to confirm the request:\r\n\r\n"
    . "https://mail-settings.google.com/mail/vf-%5BFAKECONFIRM%5D-epsx_test\r\n\r\n"
    . "click this link to cancel this verification:\r\n"
    . "https://mail-settings.google.com/mail/uf-%5BFAKECANCEL%5D-epsx_test\r\n";
$prodFound = $detector->detect($prod);
$check($prodFound !== null, 'production gmail confirmation must be detected');
$check(
    is_string($prodFound['link']) && str_contains($prodFound['link'], 'mail-settings.google.com/mail/vf-'),
    'confirm vf- link extracted, got ' . ($prodFound['link'] ?? '')
);
$check(!str_contains((string) $prodFound['link'], '/mail/uf-'), 'must not use the cancel uf- link');

$qp = "From: forwarding-noreply@google.com\r\n"
    . "Subject: Gmail Forwarding Confirmation\r\n"
    . "Content-Transfer-Encoding: quoted-printable\r\n"
    . "\r\n"
    . "has requested to automatically forward mail\r\n"
    . "https://mail-settings.google.com/mail/vf-=\r\n"
    . "QPWRAPPEDTOKEN\r\n";
$qpFound = $detector->detect($qp);
$check($qpFound !== null && is_string($qpFound['link']) && str_contains($qpFound['link'], 'QPWRAPPEDTOKEN'), 'qp-wrapped vf- link');

$root = sys_get_temp_dir() . '/dte-purge-' . uniqid();
mkdir($root . '/failed', 0700, true);
mkdir($root . '/incoming', 0700, true);
$old = $root . '/failed/old.eml';
$fresh = $root . '/failed/fresh.eml';
file_put_contents($old, 'x');
file_put_contents($fresh, 'y');
touch($old, time() - 10 * 86400);
touch($fresh, time() - 3600);

$deleted = (new IngestSpoolPurge())->purge($root, 7);
$check($deleted === 1, 'purge must delete only old files, got ' . $deleted);
$check(!is_file($old), 'old failed eml must be gone');
$check(is_file($fresh), 'fresh failed eml must remain');

foreach ([$fresh, $root . '/failed', $root . '/incoming', $root] as $path) {
    is_dir($path) ? @rmdir($path) : @unlink($path);
}

fwrite(STDOUT, "gmail_verification_and_purge_check: ok\n");
