<?php

namespace App\Services\Clinica;

class EdadPaciente
{
    public static function texto(?string $fecha, ?\DateTimeInterface $hoy = null): string
    {
        if ($fecha === null || trim($fecha) === '') {
            return 'desconocida';
        }

        $nacimiento = new \DateTimeImmutable($fecha);
        $hoy = $hoy ? \DateTimeImmutable::createFromInterface($hoy) : new \DateTimeImmutable('today');
        $nacimiento = $nacimiento->setTime(0, 0);
        $hoy = $hoy->setTime(0, 0);

        if ($nacimiento > $hoy) {
            return 'desconocida';
        }

        $diff = $nacimiento->diff($hoy);
        if ($diff->y >= 1) {
            return $diff->y === 1 ? '1 año' : $diff->y.' años';
        }
        if ($diff->m >= 1) {
            return $diff->m === 1 ? '1 mes' : $diff->m.' meses';
        }

        return $diff->d === 1 ? '1 día' : $diff->d.' días';
    }
}
