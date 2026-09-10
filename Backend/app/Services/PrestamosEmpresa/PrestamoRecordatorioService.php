<?php

namespace App\Services\PrestamosEmpresa;

use App\Models\Admin\Empresa;
use App\Models\Admin\Notificacion;
use App\Models\PrestamosEmpresa\PrestamoCuota;
use App\Models\User;
use App\Services\Funcionalidades\FuncionalidadAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PrestamoRecordatorioService
{
    public function cuotasPorVencerEn(int $dias): Collection
    {
        $fecha = Carbon::today()->addDays($dias);

        return PrestamoCuota::query()
            ->where('estado', '!=', 'pagada')
            ->whereDate('fecha_vencimiento', $fecha)
            ->whereHas('prestamo', fn ($q) => $q->where('estado', 'activo'))
            ->with('prestamo')
            ->get()
            ->filter(fn (PrestamoCuota $cuota) => $this->empresaTieneModuloPrestamos($cuota));
    }

    /**
     * Notificaciones in-app para cuotas que vencen entre $desde y $hasta (inclusive).
     */
    public function sincronizarNotificacionesInAppRango(Carbon $desde, Carbon $hasta): int
    {
        $creadas = 0;

        $cuotas = PrestamoCuota::query()
            ->where('estado', '!=', 'pagada')
            ->whereBetween('fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            ->with('prestamo')
            ->get();

        foreach ($cuotas as $cuota) {
            if ($this->sincronizarNotificacionInApp($cuota)) {
                $creadas++;
            }
        }

        return $creadas;
    }

    public function sincronizarNotificacionInApp(PrestamoCuota $cuota): bool
    {
        $prestamo = $cuota->prestamo;
        if (! $prestamo || $prestamo->estado !== 'activo' || ! $this->empresaTieneModuloPrestamos($cuota)) {
            return false;
        }

        $descripcion = $this->descripcionCuota($cuota);
        if (Notificacion::withoutGlobalScopes()->where('descripcion', $descripcion)->exists()) {
            return false;
        }

        Notificacion::create([
            'titulo' => 'Préstamo próximo a vencer',
            'descripcion' => $descripcion,
            'tipo' => 'Préstamos',
            'categoria' => 'Finanzas',
            'prioridad' => 'Alta',
            'leido' => false,
            'referencia' => 'prestamo',
            'id_referencia' => $prestamo->id,
            'id_empresa' => $prestamo->id_empresa,
        ]);

        return true;
    }

    public function descripcionCuota(PrestamoCuota $cuota): string
    {
        $prestamo = $cuota->prestamo;

        return 'Cuota #'.$cuota->numero.' del préstamo '.$prestamo->acreedor.' por $'
            .number_format((float) $cuota->total, 2).' vence el '
            .Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y').'.';
    }

    /**
     * @return Collection<int, array{email: string, name: string}>
     */
    public function destinatariosPorEmpresa(int $idEmpresa): Collection
    {
        $usuarios = User::query()
            ->where('id_empresa', $idEmpresa)
            ->where('enable', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->permission('finanzas.prestamos.pagar')
            ->get()
            ->map(fn (User $u) => ['email' => $u->email, 'name' => $u->name ?? $u->email]);

        if ($usuarios->isNotEmpty()) {
            return $usuarios->unique('email')->values();
        }

        $empresa = Empresa::find($idEmpresa);
        if ($empresa && ! empty($empresa->correo) && filter_var($empresa->correo, FILTER_VALIDATE_EMAIL)) {
            return collect([['email' => $empresa->correo, 'name' => $empresa->nombre ?? 'Empresa']]);
        }

        return collect();
    }

    public function cacheKeyCorreoEmpresa(int $idEmpresa, int $dias): string
    {
        return sprintf(
            'recordatorio_prestamo_empresa_%d_d%d_%s',
            $idEmpresa,
            $dias,
            now()->format('Y-m-d')
        );
    }

    private function empresaTieneModuloPrestamos(PrestamoCuota $cuota): bool
    {
        $prestamo = $cuota->prestamo;
        if (! $prestamo) {
            return false;
        }

        $idEmpresa = (int) $prestamo->id_empresa;

        return FuncionalidadAccess::empresaTieneSlug($idEmpresa, 'prestamos-empresa')
            && FuncionalidadAccess::empresaTieneSlug($idEmpresa, 'contabilidad');
    }
}
