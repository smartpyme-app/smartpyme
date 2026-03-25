<?php

namespace App\Services\FidelizacionCliente;

use App\Models\Admin\Empresa;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\Ventas\Clientes\Cliente;

class LicenciaFidelizacionService
{
    /**
     * Obtener la empresa efectiva para configuraciones de fidelización
     * 
     * @param Empresa $empresa
     * @return Empresa
     */
    public function getEmpresaEfectiva(Empresa $empresa): Empresa
    {
        if ($empresa->esEmpresaHija()) {
            return $empresa->getEmpresaPadre();
        }
        
        return $empresa;
    }

    /**
     * Obtener los IDs de empresas de la licencia
     * 
     * @param Empresa $empresa
     * @return array
     */
    public function getEmpresasLicenciaIds(Empresa $empresa): array
    {
        return $empresa->getEmpresasLicenciaIds();
    }

    /**
     * Verificar si una empresa tiene licencia
     * 
     * @param Empresa $empresa
     * @return bool
     */
    public function tieneLicencia(Empresa $empresa): bool
    {
        return $empresa->esEmpresaPadre() || $empresa->esEmpresaHija();
    }

    /**
     * Obtener tipos de cliente efectivos para una empresa
     * 
     * @param Empresa $empresa
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTiposClienteEfectivos(Empresa $empresa)
    {
        $empresaEfectiva = $this->getEmpresaEfectiva($empresa);
        
        return TipoClienteEmpresa::where('id_empresa', $empresaEfectiva->id)
            ->with('tipoBase')
            ->activos()
            ->orderBy('nivel')
            ->get();
    }

    /**
     * Obtener el tipo de cliente por defecto efectivo
     * 
     * @param Empresa $empresa
     * @return TipoClienteEmpresa|null
     */
    public function getTipoClienteDefaultEfectivo(Empresa $empresa): ?TipoClienteEmpresa
    {
        $empresaEfectiva = $this->getEmpresaEfectiva($empresa);
        
        return TipoClienteEmpresa::where('id_empresa', $empresaEfectiva->id)
            ->where('is_default', true)
            ->with('tipoBase')
            ->first();
    }

    /**
     * Obtener puntos de cliente considerando licencia
     * 
     * @param Cliente $cliente
     * @param Empresa $empresa
     * @return PuntosCliente|null
     */
    public function getPuntosClienteEfectivos(Cliente $cliente, Empresa $empresa): ?PuntosCliente
    {
        $empresaEfectiva = $this->getEmpresaEfectiva($empresa);
        
        return PuntosCliente::where('id_cliente', $cliente->id)
            ->where('id_empresa', $empresaEfectiva->id)
            ->first();
    }

    /**
     * Crear o actualizar puntos de cliente considerando licencia
     * 
     * @param Cliente $cliente
     * @param Empresa $empresa
     * @param array $data
     * @return PuntosCliente
     */
    public function crearOActualizarPuntosCliente(Cliente $cliente, Empresa $empresa, array $data = []): PuntosCliente
    {
        $empresaEfectiva = $this->getEmpresaEfectiva($empresa);
        
        $puntosCliente = PuntosCliente::where('id_cliente', $cliente->id)
            ->where('id_empresa', $empresaEfectiva->id)
            ->first();

        if (!$puntosCliente) {
            $puntosCliente = PuntosCliente::create(array_merge([
                'id_cliente' => $cliente->id,
                'id_empresa' => $empresaEfectiva->id,
                'puntos_disponibles' => 0,
                'puntos_totales_ganados' => 0,
                'puntos_totales_canjeados' => 0,
                'fecha_ultima_actividad' => now()
            ], $data));
        } else {
            $puntosCliente->update($data);
        }

        return $puntosCliente;
    }

    /**
     * Obtener clientes considerando licencia
     * 
     * @param Empresa $empresa
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getClientesQuery(Empresa $empresa)
    {
        if ($this->tieneLicencia($empresa)) {
            $empresasLicenciaIds = $this->getEmpresasLicenciaIds($empresa);
            return Cliente::whereIn('id_empresa', $empresasLicenciaIds);
        }
        
        return Cliente::where('id_empresa', $empresa->id);
    }

    /**
     * Obtener puntos de clientes considerando licencia
     * 
     * @param Empresa $empresa
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getPuntosClientesQuery(Empresa $empresa)
    {
        if ($this->tieneLicencia($empresa)) {
            $empresasLicenciaIds = $this->getEmpresasLicenciaIds($empresa);
            return PuntosCliente::whereIn('id_empresa', $empresasLicenciaIds);
        }
        
        return PuntosCliente::where('id_empresa', $empresa->id);
    }

    /**
     * Verificar si una empresa puede gestionar configuraciones de fidelización
     * 
     * @param Empresa $empresa
     * @return bool
     */
    public function puedeGestionarConfiguraciones(Empresa $empresa): bool
    {
        // Solo la empresa padre puede gestionar configuraciones
        return $empresa->esEmpresaPadre() || !$this->tieneLicencia($empresa);
    }

    /**
     * Obtener información de licencia para fidelización
     * 
     * @param Empresa $empresa
     * @return array
     */
    public function getInfoLicencia(Empresa $empresa): array
    {
        return [
            'tiene_licencia' => $this->tieneLicencia($empresa),
            'es_empresa_padre' => $empresa->esEmpresaPadre(),
            'es_empresa_hija' => $empresa->esEmpresaHija(),
            'empresa_efectiva_id' => $this->getEmpresaEfectiva($empresa)->id,
            'empresas_licencia_ids' => $this->getEmpresasLicenciaIds($empresa),
            'puede_gestionar_configuraciones' => $this->puedeGestionarConfiguraciones($empresa),
            'empresa_padre' => $empresa->esEmpresaHija() ? $empresa->getEmpresaPadre()->only(['id', 'nombre']) : null,
            'empresas_hijas' => $empresa->esEmpresaPadre() ? $empresa->empresasHijas()->get(['id', 'nombre']) : collect()
        ];
    }
}
