<?php

namespace App\Imports\Concerns;

trait NormalizesClienteExcelRow
{
    /**
     * @param  array<string, mixed>  $stringKeys  Claves a normalizar a string/trim
     */
    protected function applyExcelRowNormalization(array $row, array $stringKeys, bool $padMhCodigos): array
    {
        foreach ($stringKeys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $value = $row[$key];
            if ($value === null || $value === '') {
                continue;
            }
            if (is_numeric($value) && !is_string($value)) {
                $row[$key] = $this->excelNumberToStringCell($value);
            } elseif (is_string($value)) {
                $row[$key] = trim($value);
            }
        }

        if ($padMhCodigos) {
            $mhNumericWidths = [
                'cod_departamento' => 2,
                'cod_municipio' => 2,
                'cod_distrito' => 2,
                'cod_giro' => 5,
            ];
            foreach ($mhNumericWidths as $field => $width) {
                if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                    continue;
                }
                $row[$field] = $this->padMhNumericCatalogCod($row[$field], $width);
            }
        }

        $row['correo'] = $this->normalizeImportCorreo($row['correo'] ?? null);

        return $row;
    }

    /**
     * Limpia correo desde Excel: vacío → null; NBSP y control chars; primer correo si hay ; o ,;
     * Celdas con solo guiones como "sin dato"; recorta guiones al inicio/final.
     */
    private function normalizeImportCorreo($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = trim((string) $value);
        $v = str_replace("\xc2\xa0", ' ', $v);
        $v = trim($v);
        $v = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', $v);
        $v = trim($v);

        if ($v === '') {
            return null;
        }

        if (preg_match('/^[\s\-\x{2013}\x{2014}]+$/u', $v)) {
            return null;
        }

        foreach ([';', ','] as $sep) {
            if (strpos($v, $sep) === false) {
                continue;
            }
            foreach (explode($sep, $v) as $part) {
                $part = trim($part);
                if ($part !== '' && strpos($part, '@') !== false) {
                    $clean = $this->trimCorreoImportLeadingTrailingDashes($part);

                    return $clean === '' ? null : $clean;
                }
            }
        }

        $clean = $this->trimCorreoImportLeadingTrailingDashes($v);

        return $clean === '' ? null : $clean;
    }

    private function trimCorreoImportLeadingTrailingDashes(string $email): string
    {
        $email = preg_replace('/^[\s\-\x{2013}\x{2014}]+/u', '', $email);

        return preg_replace('/[\s\-\x{2013}\x{2014}]+$/u', '', $email);
    }

    private function padMhNumericCatalogCod($value, int $width): string
    {
        $v = trim((string) $value);
        if ($v === '' || !preg_match('/^\d+$/', $v) || strlen($v) >= $width) {
            return $v;
        }

        return str_pad($v, $width, '0', STR_PAD_LEFT);
    }

    private function excelNumberToStringCell($value): string
    {
        if (is_float($value) && floor($value) == $value) {
            return (string) (int) $value;
        }

        return (string) $value;
    }
}
