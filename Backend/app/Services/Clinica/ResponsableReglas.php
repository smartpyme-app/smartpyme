<?php

namespace App\Services\Clinica;

class ResponsableReglas
{
    public const ROLES = ['principal', 'secundario', 'tutor', 'contacto_emergencia'];

    public const EDAD_MENOR = 18;

    /**
     * @param  array<int, array{es_principal: bool, es_el_paciente: bool}>  $vigentes
     */
    public static function puedeCerrarAlta(string $tipo, ?int $anios, int $edadMenor, array $vigentes): ?string
    {
        $principales = 0;
        $principalEsPaciente = false;
        $hayDistinto = false;

        foreach ($vigentes as $vinculo) {
            if (! empty($vinculo['es_principal'])) {
                $principales++;
                $principalEsPaciente = ! empty($vinculo['es_el_paciente']);
            }
            if (empty($vinculo['es_el_paciente'])) {
                $hayDistinto = true;
            }
        }

        if ($principales > 1) {
            return 'Solo puede haber un responsable principal.';
        }

        if ($tipo === 'ANIMAL' && $principales !== 1) {
            return 'El animal necesita un responsable principal para cerrar el alta.';
        }

        if ($tipo === 'ANIMAL' && $principalEsPaciente) {
            return 'El responsable principal de un animal no puede ser el paciente.';
        }

        if ($tipo === 'HUMANO' && $anios !== null && $anios < $edadMenor && ! $hayDistinto) {
            return 'Un menor necesita un responsable distinto del paciente.';
        }

        return null;
    }
}
