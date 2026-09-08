<?php

namespace App\Services\MailIngest;

/**
 * Detects Gmail's forwarding-address confirmation mail.
 * Does not click the link. The UI shows code/link to the user.
 */
class GmailForwardingVerificationDetector
{
    /**
     * @return array{code: ?string, link: ?string}|null
     */
    public function detect(string $raw): ?array
    {
        [$headers, $body] = $this->split($raw);
        $from = $headers['from'] ?? '';
        $subject = $headers['subject'] ?? '';

        if (!$this->isGmailNotifier($from) || !$this->isConfirmationSubject($subject)) {
            return null;
        }

        $code = null;
        if (preg_match('/confirmation code[:\s#]+([0-9]{6,12})/i', $body, $m)
            || preg_match('/c[oó]digo de confirmaci[oó]n[:\s#]+([0-9]{6,12})/i', $body, $m)
            || preg_match('/\b([0-9]{9,12})\b/', $body, $m)) {
            $code = $m[1];
        }

        $link = null;
        if (preg_match('#https://(?:mail\.google\.com|accounts\.google\.com)[^\s<>"\']+#i', $body, $m)) {
            $link = rtrim($m[0], '.,);');
        }

        return ['code' => $code, 'link' => $link];
    }

    private function isGmailNotifier(string $from): bool
    {
        return (bool) preg_match('/forwarding-noreply@google\.com|mailer-daemon@google\.com|@google\.com/i', $from)
            && (bool) preg_match('/forwarding-noreply|mailer-daemon|noreply/i', $from);
    }

    private function isConfirmationSubject(string $subject): bool
    {
        return (bool) preg_match('/forwarding confirmation|confirmaci[oó]n de reenv[ií]o|gmail forwarding/i', $subject);
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function split(string $raw): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $parts = preg_split("/\n\n/", $normalized, 2);
        $headers = [];
        foreach (explode("\n", $parts[0] ?? '') as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return [$headers, $parts[1] ?? ''];
    }
}
