<?php

namespace App\DataTransferObjects\Compras;

/**
 * Modelo canónico de un documento electrónico importado (compra/gasto recibido).
 * Independiente del formato de origen (JSON MH, XML DGT, etc.).
 */
final class DocumentoImportDto
{
    /**
     * @param  array<string, mixed>  $identificacion
     * @param  array<string, mixed>  $emisor
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $resumen
     * @param  array<string, mixed>|string|null  $documentoOriginal
     */
    public function __construct(
        public readonly string $pais,
        public readonly string $formatoOrigen,
        public readonly array $identificacion,
        public readonly array $emisor,
        public readonly array $lineas,
        public readonly array $resumen,
        public readonly array|string|null $documentoOriginal = null,
        public readonly ?string $selloRecibido = null,
        public readonly ?string $tipoDocumentoNombre = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pais' => $this->pais,
            'formato_origen' => $this->formatoOrigen,
            'identificacion' => $this->identificacion,
            'emisor' => $this->emisor,
            'lineas' => $this->lineas,
            'resumen' => $this->resumen,
            'sello_recibido' => $this->selloRecibido,
            'tipo_documento_nombre' => $this->tipoDocumentoNombre,
        ];
    }

    /**
     * Formato compatible con la UI histórica (esquema MH El Salvador).
     *
     * @return array<string, mixed>
     */
    public function toMhCompatArray(): array
    {
        $cuerpoDocumento = [];
        foreach ($this->lineas as $linea) {
            $cuerpoDocumento[] = [
                'numItem' => $linea['numItem'] ?? null,
                'codigo' => $linea['codigo'] ?? '',
                'descripcion' => $linea['descripcion'] ?? '',
                'cantidad' => $linea['cantidad'] ?? 0,
                'precioUni' => $linea['precioUnitario'] ?? 0,
                'ventaGravada' => $linea['montoGravado'] ?? 0,
                'ventaExenta' => $linea['montoExento'] ?? 0,
                'ventaNoSuj' => $linea['montoNoSujeto'] ?? 0,
                'montoDescu' => $linea['descuento'] ?? 0,
            ];
        }

        $identificacion = [
            'fecEmi' => $this->identificacion['fechaEmision'] ?? null,
            'tipoDte' => $this->identificacion['tipoDocumento'] ?? null,
            'codigoGeneracion' => $this->identificacion['codigoGeneracion']
                ?? $this->identificacion['clave']
                ?? null,
            'numeroControl' => $this->identificacion['numeroControl']
                ?? $this->identificacion['consecutivo']
                ?? null,
            'clave' => $this->identificacion['clave'] ?? null,
        ];

        $emisor = [
            'nit' => $this->emisor['identificacion'] ?? $this->emisor['nit'] ?? null,
            'nrc' => $this->emisor['nrc'] ?? null,
            'dui' => $this->emisor['dui'] ?? null,
            'nombre' => $this->emisor['nombre'] ?? '',
            'telefono' => $this->emisor['telefono'] ?? '',
            'correo' => $this->emisor['correo'] ?? '',
            'direccion' => [
                'complemento' => $this->emisor['direccion'] ?? '',
            ],
        ];

        $resumen = [
            'subTotal' => $this->resumen['subtotal'] ?? 0,
            'subTotalVentas' => $this->resumen['subtotalVentas'] ?? $this->resumen['subtotal'] ?? 0,
            'totalGravada' => $this->resumen['totalGravado'] ?? 0,
            'totalPagar' => $this->resumen['totalPagar'] ?? $this->resumen['total'] ?? 0,
            'montoTotalOperacion' => $this->resumen['total'] ?? $this->resumen['totalPagar'] ?? 0,
            'tributos' => $this->resumen['tributos'] ?? [],
            'ivaRete1' => $this->resumen['ivaRetenido'] ?? 0,
            'ivaPerci1' => $this->resumen['ivaPercibido'] ?? 0,
            'reteRenta' => $this->resumen['rentaRetenida'] ?? 0,
            'condicionOperacion' => $this->resumen['condicionOperacion'] ?? null,
            'pagos' => $this->resumen['pagos'] ?? [],
            'totalOtrosCargos' => $this->resumen['totalOtrosCargos'] ?? 0,
        ];

        $payload = [
            'identificacion' => $identificacion,
            'emisor' => $emisor,
            'cuerpoDocumento' => $cuerpoDocumento,
            'resumen' => $resumen,
        ];

        if ($this->selloRecibido) {
            $payload['selloRecibido'] = $this->selloRecibido;
        }

        return $payload;
    }
}
