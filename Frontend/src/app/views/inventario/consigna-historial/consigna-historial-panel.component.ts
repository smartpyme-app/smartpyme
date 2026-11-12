import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgSelectModule } from '@ng-select/ng-select';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';

type AlcanceHistorial = 'ventas' | 'compras';

@Component({
  selector: 'app-consigna-historial-panel',
  standalone: true,
  imports: [CommonModule, FormsModule, NgSelectModule, CurrencyPipe],
  templateUrl: './consigna-historial-panel.component.html',
})
export class ConsignaHistorialPanelComponent implements OnChanges {
  @Input({ required: true }) alcance: AlcanceHistorial = 'ventas';
  /** Requerido en ventas. */
  @Input() idCliente: number | null = null;
  /** Etiqueta del cliente (respaldo si el API de historial no devuelve resumen). */
  @Input() nombreCliente = '';
  /** Requerido en compras. */
  @Input() idProveedor: number | null = null;

  public loading = false;
  public downloading = false;
  public resumen: any[] = [];
  public movimientos: any[] = [];
  public incluirLote = false;
  public terceroNombre = '';

  public fechas = {
    fecha_ini: '',
    fecha_fin: '',
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService
  ) {
    // ponytail: sin fecha ini el backend no recorta por rango (muestra todo el ledger del tercero)
    this.fechas.fecha_ini = '';
    this.fechas.fecha_fin = this.apiService.date();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['idCliente'] || changes['idProveedor'] || changes['alcance']) {
      if (this.terceroListo()) {
        this.cargar();
      } else {
        this.resumen = [];
        this.movimientos = [];
        this.terceroNombre = '';
      }
    }
  }

  public terceroListo(): boolean {
    return this.alcance === 'ventas' ? !!this.idCliente : !!this.idProveedor;
  }

  public cargar(): void {
    if (!this.terceroListo()) {
      return;
    }
    this.loading = true;
    const params: Record<string, unknown> = {
      alcance: this.alcance,
      ...this.parametrosFechas(),
    };
    if (this.alcance === 'ventas') {
      params['id_cliente'] = this.idCliente;
    } else {
      params['id_proveedor'] = this.idProveedor;
    }
    this.apiService.getAll('productos/consigna-historial', params).subscribe({
      next: (data) => {
        const payload = data?.resumen !== undefined ? data : (data?.data ?? data);
        this.resumen = Array.isArray(payload?.resumen) ? payload.resumen : [];
        this.movimientos = Array.isArray(payload?.movimientos) ? payload.movimientos : [];
        this.incluirLote = !!payload?.incluir_lote;
        this.terceroNombre = payload?.tercero?.nombre ?? this.nombreCliente;
        if (this.alcance === 'ventas' && this.resumen.length === 0) {
          this.complementarResumenDesdeConsignas();
        } else {
          this.loading = false;
        }
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(err);
      },
    });
  }

  /** Misma fuente que Ventas → Productos en consigna (funciona aunque el historial del ledger venga vacío). */
  private complementarResumenDesdeConsignas(): void {
    const idCliente = Number(this.idCliente);
    this.apiService.getAll('productos/consignas', { paginate: 500, page: 1 }).subscribe({
      next: (resp) => {
        const filas = resp?.data ?? (Array.isArray(resp) ? resp : []);
        const nombre = this.normalizarNombreCliente(this.nombreCliente || this.terceroNombre);
        const resumen: any[] = [];

        for (const row of filas) {
          const ventas = (row.ventas ?? []).filter((v: any) => this.ventaPerteneceAlCliente(v, idCliente, nombre));
          if (!ventas.length) {
            continue;
          }
          const disponible = ventas.reduce((s: number, v: any) => s + Number(v.cantidad ?? 0), 0);
          if (disponible <= 0) {
            continue;
          }
          resumen.push({
            id_producto: row.id,
            codigo: row.codigo,
            producto: row.nombre,
            lote_id: row.lote_id ?? null,
            lote: row.lote_numero ?? null,
            vence: row.lote_vencimiento ?? null,
            bodega: row.bodega ?? '',
            enviadas: disponible,
            facturadas: 0,
            devueltas: 0,
            disponible,
            precio_unitario: row.precio ?? 0,
          });
        }

        if (resumen.length) {
          this.resumen = resumen;
          if (filas.some((r: any) => r.agrupar_por_lote)) {
            this.incluirLote = true;
          }
        }
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      },
    });
  }

  private ventaPerteneceAlCliente(venta: any, idCliente: number, nombreNormalizado: string): boolean {
    if (idCliente > 0 && Number(venta?.id_cliente ?? 0) === idCliente) {
      return true;
    }
    if (!nombreNormalizado) {
      return false;
    }
    const etiqueta = this.normalizarNombreCliente(String(venta?.cliente ?? ''));
    return etiqueta !== '' && (etiqueta.includes(nombreNormalizado) || nombreNormalizado.includes(etiqueta));
  }

  private normalizarNombreCliente(nombre: string): string {
    return nombre
      .toLowerCase()
      .normalize('NFD')
      .replace(/\p{M}/gu, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  public exportar(): void {
    if (!this.terceroListo()) {
      this.alertService.warning(
        'Exportar',
        this.alcance === 'ventas'
          ? 'Seleccione un cliente en Filtros.'
          : 'Seleccione un proveedor en Filtros.'
      );
      return;
    }
    if (!this.apiService.hasPermission('inventario.consigna_kardex.ver')) {
      return;
    }
    this.downloading = true;
    const params: Record<string, unknown> = { alcance: this.alcance, ...this.parametrosFechas() };
    if (this.alcance === 'ventas') {
      params['id_cliente'] = this.idCliente;
    } else {
      params['id_proveedor'] = this.idProveedor;
    }
    this.apiService.export('productos/consigna-historial/exportar-pdf', params).subscribe({
      next: (blob) => {
        const nombre =
          this.alcance === 'compras' ? 'historial-consigna-compras.pdf' : 'historial-consigna-ventas.pdf';
        this.apiService.downloadFile(blob, nombre);
        this.downloading = false;
      },
      error: (err) => {
        this.downloading = false;
        if (this.resumen.length > 0 || this.movimientos.length > 0) {
          this.imprimirPdfDesdePantalla();
          this.alertService.info(
            'Exportar',
            'Si no se descargó el PDF del servidor, use «Guardar como PDF» en el cuadro de impresión.'
          );
          return;
        }
        this.alertService.error(err);
      },
    });
  }

  /** Respaldo sin servidor: misma tabla visible → imprimir / guardar como PDF. */
  private imprimirPdfDesdePantalla(): void {
    const esc = (v: unknown) =>
      String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    const thLote = this.incluirLote ? '<th>Lote</th><th>Vence</th>' : '';
    const filasResumen = this.resumen
      .map((f) => {
        const lote = this.incluirLote ? `<td>${esc(f.lote)}</td><td>${esc(f.vence)}</td>` : '';
        return `<tr>
          <td>${esc(f.codigo)}</td><td>${esc(f.producto)}</td>${lote}
          <td>${esc(f.bodega)}</td>
          <td class="r">${esc(f.enviadas)}</td><td class="r">${esc(f.facturadas)}</td>
          <td class="r">${esc(f.devueltas)}</td><td class="r">${esc(f.disponible)}</td>
          <td class="r">${esc(f.precio_unitario)}</td></tr>`;
      })
      .join('');
    const thLoteMov = this.incluirLote ? '<th>Lote</th>' : '';
    const filasMov = this.movimientos
      .map(
        (m) =>
          `<tr><td>${esc(m.fecha)}</td><td>${esc(m.tipo)}</td><td>${esc(m.producto)}</td>
          <td class="r">${esc(m.cantidad)}</td>${this.incluirLote ? `<td>${esc(m.lote)}</td>` : ''}
          <td>${esc(m.notas)}</td></tr>`
      )
      .join('');
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Historial consigna</title>
      <style>body{font-family:sans-serif;font-size:12px}table{width:100%;border-collapse:collapse;margin:12px 0}
      th,td{border:1px solid #ccc;padding:4px 6px}th{background:#f5f5f5}.r{text-align:right}h2,h3{text-align:center}</style></head>
      <body><h2>Productos en consigna</h2><h3>${esc(this.terceroNombre)}</h3>
      <table><tr><th>Código</th><th>Producto</th>${thLote}<th>Bodega</th>
      <th>Enviadas</th><th>Facturadas</th><th>Devueltas</th><th>Disp.</th><th>Pr/un</th></tr>
      ${filasResumen || '<tr><td colspan="8">Sin datos</td></tr>'}</table>
      <h2>Historial de movimientos</h2>
      <table><tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th>Cant.</th>${thLoteMov}<th>Notas</th></tr>
      ${filasMov || '<tr><td colspan="5">Sin datos</td></tr>'}</table></body></html>`;
    const ventana = window.open('', '_blank');
    if (!ventana) {
      return;
    }
    ventana.document.write(html);
    ventana.document.close();
    ventana.focus();
    ventana.print();
  }

  public etiquetaEnviadas(): string {
    return this.alcance === 'compras' ? 'Recibidas' : 'Enviadas';
  }

  public etiquetaFacturadas(): string {
    return this.alcance === 'compras' ? 'Vendidas' : 'Facturadas';
  }

  private parametrosFechas(): Record<string, string> {
    const out: Record<string, string> = {};
    if (this.fechas.fecha_ini) {
      out['fecha_ini'] = this.fechas.fecha_ini;
    }
    if (this.fechas.fecha_fin) {
      out['fecha_fin'] = this.fechas.fecha_fin;
    }
    return out;
  }
}
