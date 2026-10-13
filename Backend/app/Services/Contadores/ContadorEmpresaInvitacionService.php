<?php

namespace App\Services\Contadores;

use App\Mail\ContadorDespachoInvitacionMail;
use App\Models\Admin\Empresa;
use App\Models\Contadores\ContadorEmpresaAcceso;
use App\Models\Contadores\ContadorEmpresaInvitacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ContadorEmpresaInvitacionService
{
    public const DIAS_VIGENCIA = 7;

    /** @return array{invitacion: ContadorEmpresaInvitacion, enviado: bool} */
    public function crearYEnviar(User $invitadorContador, string $email): array
    {
        $emailNorm = strtolower(trim($email));
        if ($emailNorm === '') {
            throw new \InvalidArgumentException('Indique el correo del administrador de la empresa.', 422);
        }

        $admin = $this->resolverAdminEmpresaPorEmail($emailNorm);
        if (!$admin) {
            throw new \InvalidArgumentException(
                'No hay un administrador de empresa registrado con ese correo.',
                422
            );
        }

        $idEmpresaCliente = (int) $admin->id_empresa;
        $idEmpresaDespacho = (int) $invitadorContador->id_empresa;

        if ($idEmpresaCliente === $idEmpresaDespacho) {
            throw new \InvalidArgumentException('No puede invitar a su propia firma contable.', 422);
        }

        if ($this->despachoYaGestionaEmpresa($idEmpresaDespacho, $idEmpresaCliente)) {
            throw new \InvalidArgumentException('Esta empresa ya está en la cartera de su despacho.', 422);
        }

        ContadorEmpresaInvitacion::query()
            ->where('id_empresa_despacho', $idEmpresaDespacho)
            ->where('id_empresa_cliente', $idEmpresaCliente)
            ->where('estado', ContadorEmpresaInvitacion::ESTADO_PENDIENTE)
            ->update([
                'estado' => ContadorEmpresaInvitacion::ESTADO_CANCELADA,
                'respondida_en' => now(),
            ]);

        [$plainToken, $invitacion] = $this->crearInvitacion(
            $emailNorm,
            $idEmpresaCliente,
            $idEmpresaDespacho,
            $invitadorContador->id
        );

        $this->enviarCorreo($invitacion, $admin, $plainToken);

        return ['invitacion' => $invitacion->fresh(['empresaCliente', 'empresaDespacho']), 'enviado' => true];
    }

    public function reenviar(User $invitadorContador, int $idInvitacion): ContadorEmpresaInvitacion
    {
        $invitacion = $this->obtenerInvitacionDespacho($invitadorContador, $idInvitacion);
        if ($invitacion->estado !== ContadorEmpresaInvitacion::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('Solo se pueden reenviar invitaciones pendientes.', 422);
        }

        $admin = $this->resolverAdminEmpresaPorEmail($invitacion->email_admin);
        if (!$admin) {
            throw new \InvalidArgumentException('Ya no existe un administrador con ese correo.', 422);
        }

        $plainToken = Str::random(64);
        $invitacion->token_hash = hash('sha256', $plainToken);
        $invitacion->expira_en = now()->addDays(self::DIAS_VIGENCIA);
        $invitacion->save();

        $this->enviarCorreo($invitacion, $admin, $plainToken);

        return $invitacion->fresh(['empresaCliente', 'empresaDespacho']);
    }

    public function cancelar(User $invitadorContador, int $idInvitacion): ContadorEmpresaInvitacion
    {
        $invitacion = $this->obtenerInvitacionDespacho($invitadorContador, $idInvitacion);
        if ($invitacion->estado !== ContadorEmpresaInvitacion::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('La invitación ya no está pendiente.', 422);
        }

        $invitacion->estado = ContadorEmpresaInvitacion::ESTADO_CANCELADA;
        $invitacion->respondida_en = now();
        $invitacion->save();

        return $invitacion;
    }

    /** @return array<string, mixed> */
    public function vistaPublica(string $plainToken): array
    {
        $invitacion = $this->resolverPorToken($plainToken);
        $this->marcarExpiradaSiAplica($invitacion);

        return $this->payloadInvitacion($invitacion);
    }

    public function aceptar(string $plainToken, ?User $usuarioAutenticado = null): array
    {
        $invitacion = $this->resolverPorToken($plainToken);
        $this->marcarExpiradaSiAplica($invitacion);

        if (!$invitacion->estaVigente()) {
            throw new \InvalidArgumentException('La invitación no está disponible o expiró.', 422);
        }

        if ($usuarioAutenticado) {
            $this->assertAdminPuedeResponder($usuarioAutenticado, $invitacion);
        }

        return $this->ejecutarAceptacion($invitacion);
    }

    public function rechazar(string $plainToken, ?User $usuarioAutenticado = null): array
    {
        $invitacion = $this->resolverPorToken($plainToken);
        $this->marcarExpiradaSiAplica($invitacion);

        if ($invitacion->estado !== ContadorEmpresaInvitacion::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('La invitación ya fue respondida.', 422);
        }

        if ($usuarioAutenticado) {
            $this->assertAdminPuedeResponder($usuarioAutenticado, $invitacion);
        }

        $invitacion->estado = ContadorEmpresaInvitacion::ESTADO_RECHAZADA;
        $invitacion->respondida_en = now();
        $invitacion->save();

        return ['ok' => true];
    }

    public function rechazarDesdeApp(User $admin, int $idInvitacion): ContadorEmpresaInvitacion
    {
        $invitacion = ContadorEmpresaInvitacion::query()->findOrFail($idInvitacion);
        $this->assertAdminPuedeResponder($admin, $invitacion);

        if ($invitacion->estado !== ContadorEmpresaInvitacion::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('La invitación ya fue respondida.', 422);
        }

        $invitacion->estado = ContadorEmpresaInvitacion::ESTADO_RECHAZADA;
        $invitacion->respondida_en = now();
        $invitacion->save();

        return $invitacion;
    }

    public function aceptarDesdeApp(User $admin, int $idInvitacion): array
    {
        $invitacion = ContadorEmpresaInvitacion::query()
            ->with(['empresaCliente', 'empresaDespacho'])
            ->findOrFail($idInvitacion);
        $this->assertAdminPuedeResponder($admin, $invitacion);
        $this->marcarExpiradaSiAplica($invitacion);

        if (!$invitacion->estaVigente()) {
            throw new \InvalidArgumentException('La invitación no está disponible o expiró.', 422);
        }

        return $this->ejecutarAceptacion($invitacion);
    }

    /** Revoca accesos del despacho activo sobre la empresa del admin. */
    public function revocarDespachoActivo(User $admin): int
    {
        $idEmpresaCliente = (int) $admin->id_empresa;
        $idDespacho = $this->idEmpresaDespachoActivo($idEmpresaCliente);
        if (!$idDespacho) {
            return 0;
        }

        return $this->revocarAccesosDespachoEnEmpresa($idDespacho, $idEmpresaCliente);
    }

    /** @return array<string, mixed> */
    public function estadoParaEmpresaAdmin(User $admin): array
    {
        $idEmpresa = (int) $admin->id_empresa;

        $invitacion = ContadorEmpresaInvitacion::query()
            ->with(['empresaDespacho:id,nombre,logo'])
            ->where('id_empresa_cliente', $idEmpresa)
            ->where('estado', ContadorEmpresaInvitacion::ESTADO_PENDIENTE)
            ->orderByDesc('id')
            ->first();

        if ($invitacion) {
            $this->marcarExpiradaSiAplica($invitacion);
        }

        $invitacionPayload = null;
        if ($invitacion && $invitacion->estaVigente()) {
            $invitacionPayload = [
                'id' => $invitacion->id,
                'email_admin' => $invitacion->email_admin,
                'expira_en' => $invitacion->expira_en->toIso8601String(),
                'despacho' => $invitacion->empresaDespacho?->only(['id', 'nombre', 'logo']),
            ];
        }

        $idDespacho = $this->idEmpresaDespachoActivo($idEmpresa);
        $accesoActivo = null;
        if ($idDespacho) {
            $despacho = Empresa::query()->find($idDespacho, ['id', 'nombre', 'logo']);
            $accesoActivo = [
                'id_empresa_despacho' => $idDespacho,
                'despacho' => $despacho?->only(['id', 'nombre', 'logo']),
            ];
        }

        return [
            'invitacion_pendiente' => $invitacionPayload,
            'acceso_activo' => $accesoActivo,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, ContadorEmpresaInvitacion> */
    public function listarPendientesDespacho(int $idEmpresaDespacho)
    {
        $rows = ContadorEmpresaInvitacion::query()
            ->with(['empresaCliente:id,nombre'])
            ->where('id_empresa_despacho', $idEmpresaDespacho)
            ->where('estado', ContadorEmpresaInvitacion::ESTADO_PENDIENTE)
            ->orderByDesc('created_at')
            ->get();

        foreach ($rows as $row) {
            $this->marcarExpiradaSiAplica($row);
        }

        return $rows->filter(fn (ContadorEmpresaInvitacion $i) => $i->estaVigente())->values();
    }

    /** @return array<string, mixed> */
    private function ejecutarAceptacion(ContadorEmpresaInvitacion $invitacion): array
    {
        return DB::transaction(function () use ($invitacion) {
            $this->revocarAccesosActivosEmpresa((int) $invitacion->id_empresa_cliente);
            $creados = $this->otorgarAccesosDespacho(
                (int) $invitacion->id_empresa_despacho,
                (int) $invitacion->id_empresa_cliente,
                (int) $invitacion->id_usuario_invito
            );

            $invitacion->estado = ContadorEmpresaInvitacion::ESTADO_ACEPTADA;
            $invitacion->respondida_en = now();
            $invitacion->save();

            ContadorEmpresaInvitacion::query()
                ->where('id_empresa_cliente', $invitacion->id_empresa_cliente)
                ->where('id', '!=', $invitacion->id)
                ->where('estado', ContadorEmpresaInvitacion::ESTADO_PENDIENTE)
                ->update([
                    'estado' => ContadorEmpresaInvitacion::ESTADO_CANCELADA,
                    'respondida_en' => now(),
                ]);

            return [
                'ok' => true,
                'accesos_creados' => $creados,
                'empresa_cliente' => $invitacion->empresaCliente?->only(['id', 'nombre']),
                'empresa_despacho' => $invitacion->empresaDespacho?->only(['id', 'nombre']),
            ];
        });
    }

    private function resolverAdminEmpresaPorEmail(string $emailNorm): ?User
    {
        $rolAdmin = config('constants.ROL_ADMIN', 'admin');

        return User::query()
            ->withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [$emailNorm])
            ->whereHas('roles', fn ($q) => $q->where('name', $rolAdmin))
            ->orderBy('id')
            ->first();
    }

    private function despachoYaGestionaEmpresa(int $idEmpresaDespacho, int $idEmpresaCliente): bool
    {
        $idsContadores = User::query()
            ->withoutGlobalScopes()
            ->role(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))
            ->where('id_empresa', $idEmpresaDespacho)
            ->pluck('id');

        if ($idsContadores->isEmpty()) {
            return false;
        }

        return ContadorEmpresaAcceso::query()
            ->where('id_empresa', $idEmpresaCliente)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->whereIn('id_usuario_contador', $idsContadores)
            ->exists();
    }

    /** @return array{0: string, 1: ContadorEmpresaInvitacion} */
    private function crearInvitacion(
        string $emailNorm,
        int $idEmpresaCliente,
        int $idEmpresaDespacho,
        int $idUsuarioInvito,
    ): array {
        $plainToken = Str::random(64);

        $invitacion = ContadorEmpresaInvitacion::query()->create([
            'token_hash' => hash('sha256', $plainToken),
            'email_admin' => $emailNorm,
            'id_empresa_cliente' => $idEmpresaCliente,
            'id_empresa_despacho' => $idEmpresaDespacho,
            'id_usuario_invito' => $idUsuarioInvito,
            'estado' => ContadorEmpresaInvitacion::ESTADO_PENDIENTE,
            'expira_en' => now()->addDays(self::DIAS_VIGENCIA),
        ]);

        return [$plainToken, $invitacion];
    }

    private function enviarCorreo(ContadorEmpresaInvitacion $invitacion, User $admin, string $plainToken): void
    {
        $invitacion->loadMissing(['empresaCliente', 'empresaDespacho']);
        $frontend = rtrim((string) env('FRONTEND_URL', env('APP_URL', 'https://smartpyme.app')), '/');
        $aceptarUrl = $frontend.'/invitacion-despacho?token='.urlencode($plainToken);

        Mail::to($admin->email, $admin->name)->send(new ContadorDespachoInvitacionMail(
            $invitacion->empresaCliente,
            $invitacion->empresaDespacho,
            $admin->name,
            $aceptarUrl,
            $invitacion->expira_en->timezone('America/El_Salvador')->translatedFormat('d \d\e F \d\e Y'),
        ));
    }

    private function obtenerInvitacionDespacho(User $invitador, int $idInvitacion): ContadorEmpresaInvitacion
    {
        return ContadorEmpresaInvitacion::query()
            ->where('id_empresa_despacho', (int) $invitador->id_empresa)
            ->whereKey($idInvitacion)
            ->firstOrFail();
    }

    private function resolverPorToken(string $plainToken): ContadorEmpresaInvitacion
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            throw new \InvalidArgumentException('Token inválido.', 422);
        }

        return ContadorEmpresaInvitacion::query()
            ->with(['empresaCliente', 'empresaDespacho'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->firstOrFail();
    }

    private function marcarExpiradaSiAplica(ContadorEmpresaInvitacion $invitacion): void
    {
        if (
            $invitacion->estado === ContadorEmpresaInvitacion::ESTADO_PENDIENTE
            && $invitacion->expira_en->isPast()
        ) {
            $invitacion->estado = ContadorEmpresaInvitacion::ESTADO_EXPIRADA;
            $invitacion->respondida_en = now();
            $invitacion->save();
        }
    }

    /** @return array<string, mixed> */
    private function payloadInvitacion(ContadorEmpresaInvitacion $invitacion): array
    {
        return [
            'estado' => $invitacion->estado,
            'vigente' => $invitacion->estaVigente(),
            'expira_en' => $invitacion->expira_en?->toIso8601String(),
            'empresa_cliente' => $invitacion->empresaCliente?->only(['id', 'nombre', 'logo']),
            'empresa_despacho' => $invitacion->empresaDespacho?->only(['id', 'nombre', 'logo']),
            'email_admin' => $invitacion->email_admin,
        ];
    }

    private function assertAdminPuedeResponder(User $admin, ContadorEmpresaInvitacion $invitacion): void
    {
        $rolAdmin = config('constants.ROL_ADMIN', 'admin');
        if (!$admin->hasRole($rolAdmin)) {
            throw new \InvalidArgumentException('Solo el administrador de la empresa puede responder.', 403);
        }
        if ((int) $admin->id_empresa !== (int) $invitacion->id_empresa_cliente) {
            throw new \InvalidArgumentException('Esta invitación no corresponde a su empresa.', 403);
        }
        if (strtolower(trim((string) $admin->email)) !== strtolower(trim($invitacion->email_admin))) {
            throw new \InvalidArgumentException('Debe iniciar sesión con el correo invitado.', 403);
        }
    }

    private function revocarAccesosActivosEmpresa(int $idEmpresaCliente): void
    {
        ContadorEmpresaAcceso::query()
            ->where('id_empresa', $idEmpresaCliente)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->update([
                'estado' => ContadorEmpresaAcceso::ESTADO_REVOCADO,
                'revoked_at' => now(),
            ]);
    }

    private function otorgarAccesosDespacho(int $idEmpresaDespacho, int $idEmpresaCliente, int $invitadoPorUserId): int
    {
        $contadores = User::query()
            ->withoutGlobalScopes()
            ->role(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))
            ->where('id_empresa', $idEmpresaDespacho)
            ->get(['id']);

        $n = 0;
        foreach ($contadores as $contador) {
            ContadorEmpresaAcceso::query()->updateOrCreate(
                [
                    'id_usuario_contador' => $contador->id,
                    'id_empresa' => $idEmpresaCliente,
                ],
                [
                    'estado' => ContadorEmpresaAcceso::ESTADO_ACTIVO,
                    'permisos' => ContadorEmpresaAcceso::permisosPorDefecto(),
                    'invitado_por_user_id' => $invitadoPorUserId,
                    'revoked_at' => null,
                ]
            );
            $n++;
        }

        return $n;
    }

    private function idEmpresaDespachoActivo(int $idEmpresaCliente): ?int
    {
        $acceso = ContadorEmpresaAcceso::query()
            ->where('id_empresa', $idEmpresaCliente)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->with(['contador' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'id_empresa')])
            ->first();

        return $acceso?->contador?->id_empresa ? (int) $acceso->contador->id_empresa : null;
    }

    private function revocarAccesosDespachoEnEmpresa(int $idEmpresaDespacho, int $idEmpresaCliente): int
    {
        $idsContadores = User::query()
            ->withoutGlobalScopes()
            ->role(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))
            ->where('id_empresa', $idEmpresaDespacho)
            ->pluck('id');

        if ($idsContadores->isEmpty()) {
            return 0;
        }

        return ContadorEmpresaAcceso::query()
            ->where('id_empresa', $idEmpresaCliente)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->whereIn('id_usuario_contador', $idsContadores)
            ->update([
                'estado' => ContadorEmpresaAcceso::ESTADO_REVOCADO,
                'revoked_at' => now(),
            ]);
    }
}
