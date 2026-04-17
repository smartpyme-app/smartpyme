<?php

namespace App\Observers\FidelizacionCliente;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;

/**
 * Mantiene clientes.nivel sincronizado con id_tipo_cliente.
 */
class ClienteNivelObserver
{
    public function saving(Cliente $cliente): void
    {
        if (!$cliente->isDirty('id_tipo_cliente')) {
            return;
        }

        if ($cliente->id_tipo_cliente) {
            $tipo = TipoClienteEmpresa::withoutGlobalScopes()->find($cliente->id_tipo_cliente);
            $cliente->nivel = $tipo ? (int) $tipo->nivel : 1;
        } else {
            $cliente->nivel = 1;
        }
    }
}
