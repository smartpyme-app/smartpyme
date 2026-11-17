<?php

namespace App\Exports;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Producto;
use App\Support\Inventario\ActualizacionMasivaProductos;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActualizacionMasivaProductosExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithEvents
{
  private const COLUMNA_OCULTA_ID = 'A';

  /** @var array<int, string> */
  private array $headingsRow;

  /** @var array<int, array<int, mixed>> */
  private array $rows;

  private bool $lotesActivo;

  private ?string $columnaOcultaIdLote = null;

  public function __construct()
  {
    $idEmpresa = Auth::user()?->id_empresa;
    $empresa = $idEmpresa ? Empresa::find($idEmpresa) : null;
    $this->lotesActivo = $empresa ? $empresa->isLotesActivo() : false;

    $bodegas = $idEmpresa
      ? Bodega::where('id_empresa', $idEmpresa)
      ->where('activo', true)
      ->with('sucursal')
      ->orderBy('id_sucursal')
      ->orderBy('id')
      ->get()
      : collect();

    $columnasBodega = [];
    foreach ($bodegas as $bodega) {
      $sucursal = $bodega->sucursal ? (string) $bodega->sucursal->nombre : 'Sin sucursal';
      $columnasBodega[] = [
        'id' => (int) $bodega->id,
        'minimo' => ActualizacionMasivaProductos::encabezadoStock('stock_minimo', (int) $bodega->id, (string) $bodega->nombre, $sucursal),
        'maximo' => ActualizacionMasivaProductos::encabezadoStock('stock_maximo', (int) $bodega->id, (string) $bodega->nombre, $sucursal),
      ];
    }

        $base = ['id_creacion', 'nombre', 'codigo', 'costo', 'precio_sin_iva', 'entra_a_menu', 'genera_comanda'];
        $stockCols = array_merge(...(array_map(fn ($c) => [$c['minimo'], $c['maximo']], $columnasBodega) ?: [[]]));
    $this->headingsRow = array_merge($base, $stockCols);

    if ($this->lotesActivo) {
      $this->headingsRow = array_merge($this->headingsRow, [
        'id_lote',
        'id_bodega',
        'lote',
        'fecha_fabricacion',
        'fecha_vencimiento',
        'stock_lote',
      ]);
      $idx = array_search('id_lote', $this->headingsRow, true);
      if ($idx !== false) {
        $this->columnaOcultaIdLote = $this->indiceAColumna($idx);
      }
    }

    $relaciones = ['inventarios'];
    if ($this->lotesActivo) {
      $relaciones[] = 'lotes';
    }

    $productos = $idEmpresa
      ? Producto::where('id_empresa', $idEmpresa)
      ->where('tipo', '!=', 'Servicio')
      ->with($relaciones)
      ->orderBy('id')
      ->get()
      : collect();

    $this->rows = [];
    foreach ($productos as $producto) {
      $porBodega = $producto->inventarios->keyBy('id_bodega');
      if ($this->lotesActivo && $producto->inventario_por_lotes && $producto->lotes->isNotEmpty()) {
        foreach ($producto->lotes as $lote) {
          $this->rows[] = $this->filaProducto($producto, $columnasBodega, $porBodega, $lote);
        }
        continue;
      }
      $this->rows[] = $this->filaProducto($producto, $columnasBodega, $porBodega, null);
    }

    if ($this->rows === []) {
      $this->rows[] = array_fill(0, count($this->headingsRow), '');
    }
  }

  /**
   * @param  \Illuminate\Support\Collection<int, \App\Models\Inventario\Inventario>  $porBodega
   * @return array<int, mixed>
   */
  private function filaProducto(Producto $producto, array $columnasBodega, $porBodega, $lote): array
  {
    $fila = [
      $producto->id,
      $producto->nombre,
      $producto->codigo,
      $producto->costo,
      $producto->precio,
      $producto->mostrar_en_restaurante ? 'Si' : 'No',
      $producto->genera_comanda ? 'Si' : 'No',
    ];
    foreach ($columnasBodega as $columna) {
      $inventario = $porBodega->get($columna['id']);
      $fila[] = $inventario ? $inventario->stock_minimo : '';
      $fila[] = $inventario ? $inventario->stock_maximo : '';
    }

    if ($this->lotesActivo) {
      $fila[] = $lote ? $lote->id : '';
      $fila[] = $lote ? $lote->id_bodega : '';
      $fila[] = $lote ? $lote->numero_lote : '';
      $fila[] = $lote && $lote->fecha_fabricacion ? $lote->fecha_fabricacion->format('Y-m-d') : '';
      $fila[] = $lote && $lote->fecha_vencimiento ? $lote->fecha_vencimiento->format('Y-m-d') : '';
      $fila[] = $lote ? $lote->stock : '';
    }

    return $fila;
  }

  private function indiceAColumna(int $indice): string
  {
    $n = $indice + 1;
    $letras = '';
    while ($n > 0) {
      $n--;
      $letras = chr(65 + ($n % 26)) . $letras;
      $n = intdiv($n, 26);
    }

    return $letras;
  }

  public function headings(): array
  {
    return $this->headingsRow;
  }

  public function array(): array
  {
    return $this->rows;
  }

  public function title(): string
  {
    return 'Actualizar productos';
  }

  public function styles(Worksheet $sheet)
  {
    $sheet->getColumnDimension(self::COLUMNA_OCULTA_ID)->setVisible(false);
    if ($this->columnaOcultaIdLote !== null) {
      $sheet->getColumnDimension($this->columnaOcultaIdLote)->setVisible(false);
    }

    return [
      1 => [
        'font' => ['bold' => true],
        'fill' => [
          'fillType' => Fill::FILL_SOLID,
          'startColor' => ['rgb' => 'E2EFDA'],
        ],
      ],
    ];
  }

  public function registerEvents(): array
  {
    return [
      AfterSheet::class => function (AfterSheet $event) {
        $sheet = $event->sheet->getDelegate();
        $sheet->getColumnDimension(self::COLUMNA_OCULTA_ID)->setVisible(false);
        if ($this->columnaOcultaIdLote !== null) {
          $sheet->getColumnDimension($this->columnaOcultaIdLote)->setVisible(false);
        }
      },
    ];
  }
}
