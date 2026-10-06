<?php

namespace App\Services\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Especie;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Raza;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PacienteService
{
    public function __construct(
        private ResponsableService $responsables,
        private ExpedienteService $expedientes,
    ) {
    }
    public function crear(int $idEmpresa, ?int $idUsuario, array $datos): Paciente
    {
        return DB::transaction(function () use ($idEmpresa, $idUsuario, $datos) {
            $atributos = $this->atributos($idEmpresa, $datos);
            $atributos['id_empresa'] = $idEmpresa;
            $atributos['id_usuario'] = $idUsuario;
            $atributos['activo'] = true;

            $paciente = Paciente::create($atributos);
            $this->expedientes->abrir($idEmpresa, $paciente->id, $atributos['id_sucursal'] ?? null);

            return $paciente->fresh(['expediente', 'especie', 'raza', 'sucursal']);
        });
    }

    public function actualizar(Paciente $paciente, array $datos): Paciente
    {
        return DB::transaction(function () use ($paciente, $datos) {
            $paciente->update($this->atributos((int) $paciente->id_empresa, $datos, $paciente->id));

            return $paciente->fresh(['expediente', 'especie', 'raza', 'sucursal']);
        });
    }

    public function presentar(Paciente $paciente): array
    {
        $paciente->loadMissing(['expediente', 'especie', 'raza', 'sucursal']);
        $fecha = $paciente->fecha_nacimiento?->format('Y-m-d');
        $verExpediente = auth()->user()?->can(ClinicaPermisos::EXPEDIENTE_VER) ?? false;

        return [
            'id' => $paciente->id,
            'tipo' => $paciente->tipo,
            'activo' => (bool) $paciente->activo,
            'alta_cerrada' => (bool) $paciente->alta_cerrada,
            'nombres' => $paciente->nombres,
            'apellidos' => $paciente->apellidos,
            'nombre' => $paciente->nombre,
            'nombre_completo' => $paciente->tipo === 'HUMANO'
                ? trim(($paciente->nombres ?? '').' '.($paciente->apellidos ?? ''))
                : $paciente->nombre,
            'fecha_nacimiento' => $fecha,
            'edad' => EdadPaciente::texto($fecha),
            'sexo' => $paciente->sexo,
            'documento' => $paciente->documento,
            'telefono' => $paciente->telefono,
            'correo' => $paciente->correo,
            'direccion' => $paciente->direccion,
            'informacion_relevante' => $verExpediente ? $paciente->informacion_relevante : null,
            'color' => $paciente->color,
            'peso' => $paciente->peso,
            'microchip' => $paciente->microchip,
            'esterilizado' => $paciente->esterilizado,
            'identificadores' => $paciente->identificadores,
            'especie' => $paciente->especie ? [
                'id' => $paciente->especie->id,
                'nombre' => $paciente->especie->nombre,
            ] : null,
            'raza' => $paciente->raza ? [
                'id' => $paciente->raza->id,
                'nombre' => $paciente->raza->nombre,
            ] : null,
            'sucursal' => $paciente->sucursal ? [
                'id' => $paciente->sucursal->id,
                'nombre' => $paciente->sucursal->nombre,
            ] : null,
            'expediente' => $verExpediente && $paciente->expediente ? [
                'id' => $paciente->expediente->id,
                'numero' => $paciente->expediente->numero,
                'fecha_apertura' => $paciente->expediente->fecha_apertura?->format('Y-m-d'),
                'estado' => $paciente->expediente->estado,
                'operativo' => $this->expedientes->operativo($paciente->expediente),
            ] : null,
            'responsables' => $this->responsables->deFicha($paciente),
        ];
    }

    private function atributos(int $idEmpresa, array $datos, ?int $ignorarId = null): array
    {
        $tipo = strtoupper((string) ($datos['tipo'] ?? ''));
        if (! in_array($tipo, ['HUMANO', 'ANIMAL'], true)) {
            throw ValidationException::withMessages(['tipo' => 'El tipo de paciente no es válido.']);
        }

        $sexo = PacienteReglas::vacio($datos['sexo'] ?? null);
        $sexos = $tipo === 'HUMANO' ? PacienteReglas::SEXOS_HUMANO : PacienteReglas::SEXOS_ANIMAL;
        if ($sexo === null || ! in_array($sexo, $sexos, true)) {
            throw ValidationException::withMessages(['sexo' => 'El sexo no es válido para este tipo de paciente.']);
        }

        $fecha = PacienteReglas::vacio($datos['fecha_nacimiento'] ?? null);
        if ($fecha !== null) {
            $nacimiento = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            $errores = \DateTimeImmutable::getLastErrors();
            $invalida = $nacimiento === false
                || (is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0));
            if ($invalida || $nacimiento->format('Y-m-d') !== $fecha || $nacimiento > new \DateTimeImmutable('today')) {
                throw ValidationException::withMessages(['fecha_nacimiento' => 'La fecha de nacimiento no es válida.']);
            }
        }

        $idSucursal = $datos['id_sucursal'] ?? null;
        if ($idSucursal !== null && $idSucursal !== '') {
            $existe = Sucursal::withoutGlobalScopes()
                ->where('id', $idSucursal)
                ->where('id_empresa', $idEmpresa)
                ->exists();
            if (! $existe) {
                throw ValidationException::withMessages(['id_sucursal' => 'La sucursal no pertenece a la empresa.']);
            }
        } else {
            $idSucursal = null;
        }

        $documento = PacienteReglas::vacio($datos['documento'] ?? null);
        $microchip = PacienteReglas::vacio($datos['microchip'] ?? null);
        $this->rechazarDuplicado($idEmpresa, 'documento', $documento, $ignorarId);
        $this->rechazarDuplicado($idEmpresa, 'microchip', $microchip, $ignorarId);

        $comun = [
            'tipo' => $tipo,
            'id_sucursal' => $idSucursal,
            'fecha_nacimiento' => $fecha,
            'sexo' => $sexo,
            'telefono' => PacienteReglas::vacio($datos['telefono'] ?? null),
            'correo' => PacienteReglas::vacio($datos['correo'] ?? null),
            'direccion' => PacienteReglas::vacio($datos['direccion'] ?? null),
            'informacion_relevante' => PacienteReglas::vacio($datos['informacion_relevante'] ?? null),
        ];

        if ($tipo === 'HUMANO') {
            $nombres = PacienteReglas::vacio($datos['nombres'] ?? null);
            $apellidos = PacienteReglas::vacio($datos['apellidos'] ?? null);
            if ($nombres === null || $apellidos === null) {
                throw ValidationException::withMessages([
                    'nombres' => 'El nombre y los apellidos son obligatorios.',
                ]);
            }

            return $comun + [
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'documento' => $documento,
                'nombre' => null,
                'id_especie' => null,
                'id_raza' => null,
                'color' => null,
                'peso' => null,
                'microchip' => null,
                'esterilizado' => null,
                'identificadores' => null,
            ];
        }

        $nombre = PacienteReglas::vacio($datos['nombre'] ?? null);
        if ($nombre === null) {
            throw ValidationException::withMessages(['nombre' => 'El nombre del animal es obligatorio.']);
        }

        $idEspecie = (int) ($datos['id_especie'] ?? 0);
        $especie = Especie::withoutGlobalScopes()
            ->where('id', $idEspecie)
            ->where('id_empresa', $idEmpresa)
            ->first();
        if ($especie === null) {
            throw ValidationException::withMessages(['id_especie' => 'La especie es obligatoria y debe ser de la empresa.']);
        }

        $cantidadRazas = Raza::withoutGlobalScopes()->where('id_especie', $especie->id)->count();
        $idRaza = $datos['id_raza'] ?? null;
        $idRaza = $idRaza === '' || $idRaza === null ? null : (int) $idRaza;

        if (PacienteReglas::razaObligatoria($cantidadRazas) && $idRaza === null) {
            throw ValidationException::withMessages(['id_raza' => 'La raza es obligatoria para esta especie.']);
        }

        if ($idRaza !== null) {
            $razaValida = Raza::withoutGlobalScopes()
                ->where('id', $idRaza)
                ->where('id_especie', $especie->id)
                ->where('id_empresa', $idEmpresa)
                ->exists();
            if (! $razaValida) {
                throw ValidationException::withMessages(['id_raza' => 'La raza no corresponde a la especie.']);
            }
        }

        $peso = $datos['peso'] ?? null;
        if ($peso === '' || $peso === null) {
            $peso = null;
        } elseif (! is_numeric($peso) || (float) $peso < 0) {
            throw ValidationException::withMessages(['peso' => 'El peso no es válido.']);
        }

        return $comun + [
            'nombre' => $nombre,
            'id_especie' => $especie->id,
            'id_raza' => $idRaza,
            'color' => PacienteReglas::vacio($datos['color'] ?? null),
            'peso' => $peso,
            'microchip' => $microchip,
            'esterilizado' => array_key_exists('esterilizado', $datos) && $datos['esterilizado'] !== null && $datos['esterilizado'] !== ''
                ? filter_var($datos['esterilizado'], FILTER_VALIDATE_BOOLEAN)
                : null,
            'identificadores' => PacienteReglas::vacio($datos['identificadores'] ?? null),
            'nombres' => null,
            'apellidos' => null,
            'documento' => null,
        ];
    }

    private function rechazarDuplicado(int $idEmpresa, string $campo, ?string $valor, ?int $ignorarId): void
    {
        if ($valor === null) {
            return;
        }

        $existe = Paciente::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where($campo, $valor)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();

        if ($existe) {
            $mensaje = $campo === 'documento'
                ? 'Ese documento ya está registrado en la empresa.'
                : 'Ese microchip ya está registrado en la empresa.';
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }
}
