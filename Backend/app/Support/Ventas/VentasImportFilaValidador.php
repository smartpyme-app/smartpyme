<?php

namespace App\Support\Ventas;

class VentasImportFilaValidador
{
    public const COLUMNAS_REQUERIDAS_ARCHIVO = [
        'tipo_cliente',
        'tipo_documento_venta',
        'correlativo',
    ];

    public const TIPOS_CLIENTE = ['Persona', 'Empresa'];

    public const TIPOS_DOCUMENTO_VENTA = [
        'Factura',
        'Ticket',
        'Crédito fiscal',
        'Factura de exportación',
    ];

    public const FORMAS_PAGO = [
        'Efectivo',
        'Tarjeta de crédito/débito',
        'Cheque',
        'Transferencia',
        'Vales',
        'Chivo Wallet',
        'Bitcoin',
    ];

    public const CONDICIONES = ['Contado', 'Crédito'];

    /**
     * @param  list<string|int>  $keys
     * @return list<array{fila:int,columna:string,mensaje:string}>
     */
    public function validarEncabezados(array $keys): array
    {
        $normalizados = array_map(fn ($k) => strtolower(trim((string) $k)), $keys);
        $errores = [];
        foreach (self::COLUMNAS_REQUERIDAS_ARCHIVO as $columna) {
            if (!in_array($columna, $normalizados, true)) {
                $errores[] = $this->error(1, $columna, 'falta en el archivo. Use la plantilla unificada de ventas.');
            }
        }

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return list<array{fila:int,columna:string,mensaje:string}>
     */
    public function validarFila(array $fila, int $filaExcel): array
    {
        $fila = $this->normalizarClaves($fila);
        $errores = [];

        $tipoCliente = $this->celda($fila, 'tipo_cliente');
        if (!$this->enLista($tipoCliente, self::TIPOS_CLIENTE)) {
            $errores[] = $this->error(
                $filaExcel,
                'tipo_cliente',
                $tipoCliente === '' ? 'es obligatorio (Persona o Empresa).' : 'debe ser Persona o Empresa.'
            );
        }

        $tipoDocVenta = $this->celda($fila, 'tipo_documento_venta');
        if (!$this->enLista($tipoDocVenta, self::TIPOS_DOCUMENTO_VENTA)) {
            $errores[] = $this->error(
                $filaExcel,
                'tipo_documento_venta',
                $tipoDocVenta === ''
                    ? 'es obligatorio (Factura, Ticket, Crédito fiscal o Factura de exportación).'
                    : 'debe ser Factura, Ticket, Crédito fiscal o Factura de exportación.'
            );
        }

        if ($this->celda($fila, 'correlativo') === '') {
            $errores[] = $this->error($filaExcel, 'correlativo', 'es obligatorio (ventas históricas).');
        }

        if ($this->celda($fila, 'nombre') === '') {
            $errores[] = $this->error($filaExcel, 'nombre', 'es obligatorio.');
        }

        if ($this->celda($fila, 'fecha') === '') {
            $errores[] = $this->error($filaExcel, 'fecha', 'es obligatorio.');
        }

        if ($this->celda($fila, 'descripcion') === '') {
            $errores[] = $this->error($filaExcel, 'descripcion', 'es obligatorio.');
        }

        $total = $this->celda($fila, 'total');
        if ($total === '' || !is_numeric($total)) {
            $errores[] = $this->error($filaExcel, 'total', 'es obligatorio y debe ser numérico.');
        }

        $formaPago = $this->celda($fila, 'forma_pago');
        if (!$this->enLista($formaPago, self::FORMAS_PAGO)) {
            $errores[] = $this->error(
                $filaExcel,
                'forma_pago',
                $formaPago === ''
                    ? 'es obligatorio.'
                    : 'debe ser Efectivo, Tarjeta de crédito/débito, Cheque, Transferencia, Vales, Chivo Wallet o Bitcoin.'
            );
        }

        $condicion = $this->celda($fila, 'condicion');
        if ($condicion !== '' && !$this->enLista($condicion, self::CONDICIONES)) {
            $errores[] = $this->error($filaExcel, 'condicion', 'debe ser Contado o Crédito.');
        }
        if ($this->esCredito($condicion) && $this->celda($fila, 'fecha_pago') === '') {
            $errores[] = $this->error($filaExcel, 'fecha_pago', 'es obligatorio cuando condicion es Crédito.');
        }

        if ($this->esCreditoFiscal($tipoDocVenta)) {
            if ($this->celda($fila, 'nit') === '') {
                $errores[] = $this->error($filaExcel, 'nit', 'obligatorio porque tipo_documento_venta es Crédito fiscal.');
            }
            if ($this->celda($fila, 'nrc') === '') {
                $errores[] = $this->error($filaExcel, 'nrc', 'obligatorio porque tipo_documento_venta es Crédito fiscal.');
            }
        }

        $esPersona = $this->igual($tipoCliente, 'Persona');
        $nombre = $this->celda($fila, 'nombre');
        if ($esPersona && !$this->esCreditoFiscal($tipoDocVenta) && !$this->esConsumidorFinal($nombre)) {
            if ($this->celda($fila, 'tipo_documento') === '') {
                $errores[] = $this->error($filaExcel, 'tipo_documento', 'es obligatorio para Persona (DUI, NIT, Pasaporte, etc.).');
            }
            if ($this->celda($fila, 'num_documento') === '') {
                $errores[] = $this->error($filaExcel, 'num_documento', 'es obligatorio para Persona.');
            }
        }

        return $errores;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{fila:int,columna:string,mensaje:string}>
     */
    public function validarAgrupacion(array $filas): array
    {
        $porCorrelativo = [];
        foreach ($filas as $fila) {
            $fila = $this->normalizarClaves($fila);
            $corr = $this->celda($fila, 'correlativo');
            if ($corr === '') {
                continue;
            }
            $porCorrelativo[$corr][] = $fila;
        }

        $errores = [];
        foreach ($porCorrelativo as $filasGrupo) {
            if (count($filasGrupo) < 2) {
                continue;
            }
            $identidades = [];
            $tiposDoc = [];
            foreach ($filasGrupo as $fila) {
                $identidades[$this->identidadCliente($fila)] = true;
                $tiposDoc[$this->norm($this->celda($fila, 'tipo_documento_venta'))] = true;
            }
            if (count($identidades) > 1 || count($tiposDoc) > 1) {
                foreach ($filasGrupo as $fila) {
                    $excel = (int) ($fila['fila'] ?? 0);
                    $errores[] = $this->error(
                        $excel,
                        'correlativo',
                        'el mismo correlativo no puede usarse con distintos clientes o tipos de documento.'
                    );
                }
            }
        }

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public function claveAgrupacion(array $fila): string
    {
        $fila = $this->normalizarClaves($fila);

        return implode('|', [
            $this->celda($fila, 'correlativo'),
            $this->norm($this->celda($fila, 'tipo_documento_venta')),
            $this->identidadCliente($fila),
        ]);
    }

    public function tipoItemDetalle(): string
    {
        return 'Servicio';
    }

    public function esCreditoFiscal(string $tipoDocumentoVenta): bool
    {
        return $this->norm($tipoDocumentoVenta) === 'credito fiscal';
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public function tipoCliente(array $fila): string
    {
        $fila = $this->normalizarClaves($fila);
        $tipo = $this->celda($fila, 'tipo_cliente');

        return $this->igual($tipo, 'Empresa') ? 'Empresa' : 'Persona';
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    public function normalizarClaves(array $fila): array
    {
        $out = [];
        foreach ($fila as $k => $v) {
            $out[strtolower(trim((string) $k))] = $v;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function identidadCliente(array $fila): string
    {
        $fila = $this->normalizarClaves($fila);
        if ($this->esCreditoFiscal($this->celda($fila, 'tipo_documento_venta')) || $this->igual($this->celda($fila, 'tipo_cliente'), 'Empresa')) {
            $nit = $this->celda($fila, 'nit');
            $nrc = $this->celda($fila, 'nrc');
            if ($nit !== '' || $nrc !== '') {
                return 'nit:' . $nit . '|nrc:' . $nrc;
            }
        }
        $num = $this->celda($fila, 'num_documento');
        if ($num !== '') {
            return 'doc:' . $num;
        }

        return 'nombre:' . $this->norm($this->celda($fila, 'nombre'));
    }

    /**
     * @return array{fila:int,columna:string,mensaje:string}
     */
    private function error(int $fila, string $columna, string $mensaje): array
    {
        return ['fila' => $fila, 'columna' => $columna, 'mensaje' => $mensaje];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function celda(array $fila, string $campo): string
    {
        if (!array_key_exists($campo, $fila) || $fila[$campo] === null) {
            return '';
        }

        return trim((string) $fila[$campo]);
    }

    /**
     * @param  list<string>  $lista
     */
    private function enLista(string $valor, array $lista): bool
    {
        if ($valor === '') {
            return false;
        }
        foreach ($lista as $opcion) {
            if ($this->igual($valor, $opcion)) {
                return true;
            }
        }

        return false;
    }

    private function igual(string $a, string $b): bool
    {
        return $this->norm($a) === $this->norm($b);
    }

    private function esCredito(string $condicion): bool
    {
        return $this->norm($condicion) === 'credito';
    }

    private function esConsumidorFinal(string $nombre): bool
    {
        return $this->norm($nombre) === 'consumidor final';
    }

    private function norm(string $valor): string
    {
        $valor = trim(mb_strtolower($valor));
        $from = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
        $to = ['a', 'e', 'i', 'o', 'u', 'u', 'n'];

        return str_replace($from, $to, $valor);
    }
}
