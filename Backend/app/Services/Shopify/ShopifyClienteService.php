<?php

namespace App\Services\Shopify;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Support\Facades\Log;

class ShopifyClienteService
{
    /**
     * Busca o actualiza un cliente optimizando por shopify_customer_id, correo y teléfono
     *
     * @param array $clienteData
     * @param int $empresaId
     * @return Cliente
     */
    public function buscarOActualizarCliente(array $clienteData, int $empresaId): Cliente
    {
        $shopifyCustomerId = $clienteData['shopify_customer_id'] ?? null;
        $correo = $clienteData['correo'] ?? null;
        $telefono = $clienteData['telefono'] ?? null;

        // Validaciones de seguridad para evitar asignaciones incorrectas
        if (!$this->validarDatosCliente($clienteData)) {
            Log::warning('Datos de cliente inválidos, creando cliente con datos mínimos', [
                'shopify_customer_id' => $shopifyCustomerId,
                'correo' => $correo,
                'telefono' => $telefono
            ]);

            // Crear cliente con datos mínimos válidos
            return $this->crearClienteMinimo($clienteData, $empresaId);
        }

        // 1. Si tenemos shopify_customer_id, buscar primero por ese campo
        if ($shopifyCustomerId) {
            $cliente = Cliente::where('shopify_customer_id', $shopifyCustomerId)
                ->where('id_empresa', $empresaId)
                ->first();

            if ($cliente) {
                Log::info('Cliente encontrado por shopify_customer_id', [
                    'cliente_id' => $cliente->id,
                    'shopify_customer_id' => $shopifyCustomerId,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono
                ]);

                // Actualizar datos del cliente excluyendo campos protegidos
                $clienteDataProtegido = $this->excluirCamposProtegidos($clienteData, $empresaId);
                $cliente->update($clienteDataProtegido);
                return $cliente;
            }
        }

        // 2. Si no se encontró por shopify_customer_id, buscar por correo
        if ($correo) {
            $cliente = Cliente::where('correo', $correo)
                ->where('id_empresa', $empresaId)
                ->first();

            if ($cliente) {
                // Validar que no haya conflicto con shopify_customer_id existente
                if ($cliente->shopify_customer_id && $cliente->shopify_customer_id !== $shopifyCustomerId) {
                    Log::warning('Conflicto de shopify_customer_id detectado', [
                        'cliente_id' => $cliente->id,
                        'correo' => $correo,
                        'shopify_customer_id_existente' => $cliente->shopify_customer_id,
                        'shopify_customer_id_nuevo' => $shopifyCustomerId
                    ]);

                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }

                Log::info('Cliente encontrado por correo, actualizando shopify_customer_id', [
                    'cliente_id' => $cliente->id,
                    'correo' => $correo,
                    'shopify_customer_id' => $shopifyCustomerId,
                    'telefono_actual' => $cliente->telefono,
                    'telefono_nuevo' => $telefono
                ]);

                // Actualizar datos incluyendo el shopify_customer_id, excluyendo campos protegidos
                $clienteDataProtegido = $this->excluirCamposProtegidos($clienteData, $empresaId);
                $cliente->update($clienteDataProtegido);
                return $cliente;
            }
        }

        // 3. Si no se encontró por correo, buscar por teléfono (con validación adicional)
        if ($telefono) {
            $cliente = Cliente::where('telefono', $telefono)
                ->where('id_empresa', $empresaId)
                ->first();

            if ($cliente) {
                // Validar que no haya conflicto con shopify_customer_id existente
                if ($cliente->shopify_customer_id && $cliente->shopify_customer_id !== $shopifyCustomerId) {
                    Log::warning('Conflicto de shopify_customer_id detectado por teléfono', [
                        'cliente_id' => $cliente->id,
                        'telefono' => $telefono,
                        'shopify_customer_id_existente' => $cliente->shopify_customer_id,
                        'shopify_customer_id_nuevo' => $shopifyCustomerId
                    ]);

                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }

                // Validar que el correo coincida si está disponible
                if ($correo && $cliente->correo && $cliente->correo !== $correo) {
                    Log::warning('Conflicto de correo detectado por teléfono', [
                        'cliente_id' => $cliente->id,
                        'telefono' => $telefono,
                        'correo_cliente' => $cliente->correo,
                        'correo_pedido' => $correo
                    ]);

                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }

                Log::info('Cliente encontrado por teléfono, actualizando shopify_customer_id', [
                    'cliente_id' => $cliente->id,
                    'telefono' => $telefono,
                    'shopify_customer_id' => $shopifyCustomerId,
                    'correo_actual' => $cliente->correo,
                    'correo_nuevo' => $correo
                ]);

                // Actualizar datos incluyendo el shopify_customer_id, excluyendo campos protegidos
                $clienteDataProtegido = $this->excluirCamposProtegidos($clienteData, $empresaId);
                $cliente->update($clienteDataProtegido);
                return $cliente;
            }
        }

        // 4. Si no existe, crear nuevo cliente
        Log::info('Creando nuevo cliente', [
            'correo' => $correo,
            'telefono' => $telefono,
            'shopify_customer_id' => $shopifyCustomerId
        ]);

        return Cliente::create($clienteData);
    }

    /**
     * Excluye campos protegidos del array de datos del cliente
     * Estos campos no deben ser actualizados desde Shopify cuando el cliente ya existe
     * Solo aplica si la empresa tiene facturación electrónica activa
     *
     * @param array $clienteData
     * @param int $empresaId
     * @return array
     */
    public function excluirCamposProtegidos(array $clienteData, int $empresaId): array
    {
        // Verificar si la empresa tiene facturación electrónica activa
        $empresa = Empresa::find($empresaId);

        if (!$empresa || !$empresa->facturacion_electronica) {
            // Si no tiene facturación electrónica, retornar todos los datos sin excluir nada
            Log::info('Empresa sin facturación electrónica - no se excluyen campos protegidos', [
                'empresa_id' => $empresaId,
                'facturacion_electronica' => $empresa ? $empresa->facturacion_electronica : false
            ]);
            return $clienteData;
        }

        // Campos que no deben ser actualizados desde Shopify cuando el cliente ya existe
        // Estos campos son importantes para facturación de crédito fiscal
        $camposProtegidos = [
            'cod_departamento',
            'departamento',
            'municipio',
            'cod_municipio',
            'pais'
        ];

        // Crear una copia del array sin los campos protegidos
        $clienteDataProtegido = $clienteData;
        foreach ($camposProtegidos as $campo) {
            unset($clienteDataProtegido[$campo]);
        }

        return $clienteDataProtegido;
    }

    /**
     * Valida los datos del cliente para evitar asignaciones incorrectas
     *
     * @param array $clienteData
     * @return bool
     */
    public function validarDatosCliente(array $clienteData): bool
    {
        $correo = $clienteData['correo'] ?? null;
        $nombre = $clienteData['nombre'] ?? null;
        $apellido = $clienteData['apellido'] ?? null;

        // Validar que tenga al menos un nombre
        if (empty($nombre) && empty($apellido)) {
            Log::warning('Cliente sin nombre válido', [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'correo' => $correo
            ]);
            return false;
        }

        // Validar email si existe
        if ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Email inválido', [
                'correo' => $correo,
                'nombre' => $nombre
            ]);
            return false;
        }

        // Validar que no sea un email genérico o de prueba
        if ($correo && $this->esEmailGenerico($correo)) {
            Log::warning('Email genérico detectado', [
                'correo' => $correo,
                'nombre' => $nombre
            ]);
            return false;
        }

        return true;
    }

    /**
     * Verifica si un email es genérico o de prueba
     *
     * @param string $email
     * @return bool
     */
    public function esEmailGenerico(string $email): bool
    {
        $emailsGenericos = [
            'test@example.com',
            'test@test.com',
            'admin@shopify.com',
            'noreply@shopify.com',
            'support@shopify.com',
            'info@shopify.com',
            'contact@shopify.com'
        ];

        $emailLower = strtolower($email);

        // Verificar emails genéricos exactos
        if (in_array($emailLower, $emailsGenericos)) {
            return true;
        }

        // Verificar patrones genéricos
        $patronesGenericos = [
            '/^test\d*@/',
            '/^admin\d*@/',
            '/^user\d*@/',
            '/^customer\d*@/',
            '/^shopify\d*@/',
            '/^demo\d*@/'
        ];

        foreach ($patronesGenericos as $patron) {
            if (preg_match($patron, $emailLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Crea un cliente con datos mínimos válidos
     *
     * @param array $clienteData
     * @param int $empresaId
     * @return Cliente
     */
    public function crearClienteMinimo(array $clienteData, int $empresaId): Cliente
    {
        $clienteMinimo = [
            'nombre' => $clienteData['nombre'] ?? 'Cliente',
            'apellido' => $clienteData['apellido'] ?? 'Shopify',
            'correo' => $clienteData['correo'] ?? 'cliente@shopify.com',
            'telefono' => $clienteData['telefono'] ?? '',
            'direccion' => $clienteData['direccion'] ?? '',
            'pais' => $clienteData['pais'] ?? '',
            'municipio' => $clienteData['municipio'] ?? '',
            'departamento' => $clienteData['departamento'] ?? '',
            'tipo' => 'Persona',
            'enable' => 1,
            'id_empresa' => $empresaId,
            'id_usuario' => $clienteData['id_usuario'] ?? null,
            'shopify_customer_id' => $clienteData['shopify_customer_id'] ?? null,
        ];

        Log::info('Creando cliente mínimo', [
            'cliente_minimo' => $clienteMinimo
        ]);

        return Cliente::create($clienteMinimo);
    }

    /**
     * Obtiene o crea el cliente "Consumidor Final"
     *
     * @param int $empresaId
     * @return Cliente
     */
    public function obtenerClienteConsumidorFinal(int $empresaId): Cliente
    {
        // Buscar cliente "Consumidor Final" existente
        $cliente = Cliente::where('nombre', 'Consumidor Final')
            ->where('apellido', '')
            ->where('id_empresa', $empresaId)
            ->first();

        if (!$cliente) {
            // Crear cliente "Consumidor Final" si no existe
            $cliente = Cliente::create([
                'nombre' => 'Consumidor Final',
                'apellido' => '',
                'correo' => '',
                'telefono' => '',
                'direccion' => '',
                'pais' => '',
                'municipio' => '',
                'departamento' => '',
                'tipo' => 'Persona',
                'enable' => 1,
                'id_empresa' => $empresaId,
                'id_usuario' => null, // No asociado a usuario específico
            ]);

            Log::info('Cliente Consumidor Final creado', [
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresaId
            ]);
        }

        return $cliente;
    }
}

