<?php

namespace App\Services\MailIngest;

/**
 * Minimal RFC822 attachment extractor. No Laravel.
 */
class InboundMimeParser
{
    /**
     * @return list<array{filename: string, content: string}>
     */
    public function attachments(string $raw): array
    {
        return $this->collectParts($this->parsePart($raw));
    }

    public function messageId(string $raw): ?string
    {
        [$headers] = $this->splitHeaders($raw);
        $id = $this->header($headers, 'message-id');

        return $id !== '' ? $id : null;
    }

    /**
     * @return list<array{filename: string, content: string}>
     */
    private function collectParts(array $part): array
    {
        if ($part['parts'] !== []) {
            $found = [];
            foreach ($part['parts'] as $child) {
                $found = array_merge($found, $this->collectParts($child));
            }

            return $found;
        }

        $filename = $part['filename'];
        if ($filename === '') {
            return [];
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['json', 'pdf', 'xml'], true)) {
            return [];
        }

        return [['filename' => $filename, 'content' => $part['body']]];
    }

    /**
     * @return array{filename: string, body: string, parts: list<array>}
     */
    private function parsePart(string $raw): array
    {
        [$headers, $body] = $this->splitHeaders($raw);
        $contentType = $this->header($headers, 'content-type');
        $disposition = $this->header($headers, 'content-disposition');
        $encoding = strtolower($this->header($headers, 'content-transfer-encoding'));
        $filename = $this->filenameFrom($disposition, $contentType);
        $boundary = $this->parameter($contentType, 'boundary');

        if ($boundary !== '' && stripos($contentType, 'multipart/') === 0) {
            $parts = [];
            foreach ($this->splitMultipart($body, $boundary) as $childRaw) {
                $parts[] = $this->parsePart($childRaw);
            }

            return ['filename' => '', 'body' => '', 'parts' => $parts];
        }

        return [
            'filename' => $filename,
            'body' => $this->decodeBody($body, $encoding),
            'parts' => [],
        ];
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function splitHeaders(string $raw): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $split = preg_split("/\n\n/", $normalized, 2);
        $headerText = $split[0] ?? '';
        $body = $split[1] ?? '';
        $headers = [];
        $current = '';
        foreach (explode("\n", $headerText) as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $current .= ' ' . trim($line);
                continue;
            }
            if ($current !== '') {
                $this->storeHeader($headers, $current);
            }
            $current = $line;
        }
        if ($current !== '') {
            $this->storeHeader($headers, $current);
        }

        return [$headers, $body];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function storeHeader(array &$headers, string $line): void
    {
        $pos = strpos($line, ':');
        if ($pos === false) {
            return;
        }
        $name = strtolower(trim(substr($line, 0, $pos)));
        $headers[$name] = trim(substr($line, $pos + 1));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function header(array $headers, string $name): string
    {
        return $headers[strtolower($name)] ?? '';
    }

    private function filenameFrom(string $disposition, string $contentType): string
    {
        $name = $this->parameter($disposition, 'filename');
        if ($name === '') {
            $name = $this->parameter($contentType, 'name');
        }

        return $name;
    }

    private function parameter(string $header, string $name): string
    {
        if (preg_match('/(?:^|;)\s*' . preg_quote($name, '/') . '\s*=\s*"([^"]+)"/i', $header, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/(?:^|;)\s*' . preg_quote($name, '/') . '\s*=\s*([^;]+)/i', $header, $m)) {
            return trim($m[1], " \t\"");
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function splitMultipart(string $body, string $boundary): array
    {
        $parts = preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?\s*/', $body) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || str_starts_with($part, '--')) {
                continue;
            }
            $out[] = $part;
        }

        return $out;
    }

    private function decodeBody(string $body, string $encoding): string
    {
        $body = str_replace("\r\n", "\n", $body);
        if ($encoding === 'base64') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true);

            return $decoded === false ? '' : $decoded;
        }
        if ($encoding === 'quoted-printable') {
            return quoted_printable_decode($body);
        }

        return $body;
    }
}
