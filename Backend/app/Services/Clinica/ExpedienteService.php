<?php

namespace App\Services\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Expediente;
use App\Models\Clinica\Paciente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpedienteService
{
    public function __construct(private HistorialClinicoService $historial)
    {
    }

    /** @var list<array{slug: string, nombre: string, disponible: bool}> */
    public const SECCIONES = [
        ['slug' => 'historial', 'nombre' => 'Historial clínico', 'disponible' => true],
        ['slug' => 'consultas', 'nombre' => 'Consultas', 'disponible' => true],
        ['slug' => 'documentos', 'nombre' => 'Documentos', 'disponible' => false],
        ['slug' => 'diagnosticos', 'nombre' => 'Diagnósticos', 'disponible' => false],
        ['slug' => 'tratamientos', 'nombre' => 'Tratamientos', 'disponible' => false],
        ['slug' => 'medicamentos', 'nombre' => 'Medicamentos', 'disponible' => false],
        ['slug' => 'vacunas', 'nombre' => 'Vacunas y preventivos', 'disponible' => false],
        ['slug' => 'estudios', 'nombre' => 'Estudios', 'disponible' => false],
        ['slug' => 'laboratorio', 'nombre' => 'Laboratorio', 'disponible' => false],
        ['slug' => 'imagenes', 'nombre' => 'Imágenes', 'disponible' => false],
        ['slug' => 'cirugias', 'nombre' => 'Cirugías', 'disponible' => false],
        ['slug' => 'hospitalizaciones', 'nombre' => 'Hospitalizaciones', 'disponible' => false],
        ['slug' => 'seguimiento', 'nombre' => 'Seguimiento', 'disponible' => false],
    ];

    public function dePaciente(Paciente $paciente): Expediente
    {
        $expediente = Expediente::where('id_paciente', $paciente->id)->first();
        if ($expediente === null) {
            throw ValidationException::withMessages(['expediente' => 'Este paciente no tiene expediente clínico.']);
        }

        return $expediente;
    }

    public function presentar(Expediente $expediente): array
    {
        $expediente->loadMissing([
            'paciente' => fn ($q) => $q->with(['especie', 'raza']),
            'sucursalApertura',
        ]);
        $paciente = $expediente->paciente;

        return [
            'id' => $expediente->id,
            'numero' => $expediente->numero,
            'fecha_apertura' => $expediente->fecha_apertura?->format('Y-m-d'),
            'estado' => $expediente->estado,
            'operativo' => $this->operativo($expediente),
            'id_paciente' => $expediente->id_paciente,
            'paciente' => $paciente ? [
                'id' => $paciente->id,
                'nombre_completo' => $paciente->tipo === 'HUMANO'
                    ? trim(($paciente->nombres ?? '').' '.($paciente->apellidos ?? ''))
                    : $paciente->nombre,
                'tipo' => $paciente->tipo,
                'activo' => (bool) $paciente->activo,
            ] : null,
            'sucursal_apertura' => $expediente->sucursalApertura ? [
                'id' => $expediente->sucursalApertura->id,
                'nombre' => $expediente->sucursalApertura->nombre,
            ] : null,
            'secciones' => self::SECCIONES,
        ];
    }

    public function cambiarEstado(Expediente $expediente, string $estado): Expediente
    {
        $estado = strtolower($estado);
        if (! in_array($estado, ['abierto', 'archivado'], true)) {
            throw ValidationException::withMessages(['estado' => 'El estado del expediente no es válido.']);
        }

        $expediente->estado = $estado;
        $expediente->save();

        return $expediente->fresh(['sucursalApertura', 'paciente']);
    }

    public function operativo(?Expediente $expediente): bool
    {
        return $expediente !== null && $expediente->estado === 'abierto';
    }

    public function exigirOperativo(Paciente $paciente): void
    {
        $expediente = Expediente::where('id_paciente', $paciente->id)->first();
        if (! $this->operativo($expediente)) {
            throw ValidationException::withMessages([
                'expediente' => 'El expediente está archivado. Reábralo para registrar cambios clínicos operativos.',
            ]);
        }
    }

    public function abrir(int $idEmpresa, int $idPaciente, ?int $idSucursalApertura): Expediente
    {
        if (Expediente::withoutGlobalScopes()->where('id_paciente', $idPaciente)->where('id_empresa', $idEmpresa)->exists()) {
            throw ValidationException::withMessages(['expediente' => 'Este paciente ya tiene expediente en la empresa.']);
        }

        $idSucursalApertura = $this->normalizarSucursalApertura($idEmpresa, $idSucursalApertura);

        return DB::transaction(function () use ($idEmpresa, $idPaciente, $idSucursalApertura) {
            DB::table('clinica_expediente_secuencias')->insertOrIgnore([
                'id_empresa' => $idEmpresa,
                'ultimo' => 0,
            ]);

            $fila = DB::table('clinica_expediente_secuencias')
                ->where('id_empresa', $idEmpresa)
                ->lockForUpdate()
                ->first();

            $numero = ExpedienteNumero::siguiente((int) $fila->ultimo);

            DB::table('clinica_expediente_secuencias')
                ->where('id_empresa', $idEmpresa)
                ->update(['ultimo' => $numero]);

            $expediente = Expediente::create([
                'id_empresa' => $idEmpresa,
                'id_paciente' => $idPaciente,
                'id_sucursal_apertura' => $idSucursalApertura,
                'numero' => $numero,
                'fecha_apertura' => now()->toDateString(),
                'estado' => 'abierto',
            ]);
            $this->historial->registrarApertura($expediente);

            return $expediente;
        });
    }

    private function normalizarSucursalApertura(int $idEmpresa, ?int $idSucursal): ?int
    {
        if ($idSucursal === null) {
            return null;
        }

        $valida = Sucursal::withoutGlobalScopes()
            ->where('id', $idSucursal)
            ->where('id_empresa', $idEmpresa)
            ->exists();

        return $valida ? $idSucursal : null;
    }
}
