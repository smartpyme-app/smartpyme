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
        $text = $this->flatten($raw);
        if (!$this->looksLikeConfirmation($text)) {
            return null;
        }

        return [
            'code' => $this->extractCode($text),
            'link' => $this->extractConfirmLink($text),
        ];
    }

    private function looksLikeConfirmation(string $text): bool
    {
        return (bool) preg_match(
            '/has requested to automatically forward mail|forwarding confirmation|confirmaci[oó]n de reenv[ií]o|gmail forwarding confirmation|mail-settings\.google\.com\/mail\/vf-|mail\.google\.com\/mail\/vf-/i',
            $text
        );
    }

    private function extractCode(string $text): ?string
    {
        if (preg_match('/confirmation code[:\s#]+([0-9]{6,12})/i', $text, $m)
            || preg_match('/c[oó]digo de confirmaci[oó]n[:\s#]+([0-9]{6,12})/i', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractConfirmLink(string $text): ?string
    {
        $flat = preg_replace('/\s+/', '', $text) ?? $text;
        $flat = html_entity_decode($flat, ENT_QUOTES);

        if (preg_match('#https://(?:mail-settings\.google\.com|mail\.google\.com|accounts\.google\.com)/mail/vf-[A-Za-z0-9._%\-\[\]]+#i', $flat, $m)) {
            return $m[0];
        }

        if (preg_match('#https://(?:mail-settings\.google\.com|mail\.google\.com|accounts\.google\.com)[A-Za-z0-9._/%\-?=&#\[\]]+#i', $flat, $m)) {
            $link = $m[0];
            if (!str_contains(strtolower($link), '/mail/uf-')) {
                return $link;
            }
        }

        return null;
    }

    private function flatten(string $raw): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        // ponytail: QP soft-break only. Ceiling: full MIME decode if Gmail nests the vf- link in a part we flatten badly.
        $text = preg_replace("/=\n/", '', $text) ?? $text;

        return preg_replace_callback('/=([0-9A-F]{2})/i', static function (array $m): string {
            return chr(hexdec($m[1]));
        }, $text) ?? $text;
    }
}
