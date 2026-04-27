import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';
import { LibroIvaCrDetalleTableComponent } from '@views/contabilidad/libro-iva/costa-rica/libro-iva-cr-detalle-table.component';
import * as moment from 'moment';

/**
 * Libros de IVA — países distintos de El Salvador.
 * Costa Rica: detalle IVA según plantillas Reporte_Detalle_IVA / Reporte_Detalle_IVA_Compras.
 * Otros: resumen tipo Honduras (consumidores / compras) y retenciones SV si aplica.
 */
@Component({
  selector: 'app-libro-iva-general',
  templateUrl: './libro-iva-general.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, LibroIvaCrDetalleTableComponent],
})
export class LibroIvaGeneralComponent implements OnInit {
  activoSeccion: 'ventas' | 'compras' | 'retenciones' = 'ventas';
  ventas: any[] = [];
  compras: any[] = [];
  retenciones: any[] = [];
  /** Totales numéricos del backend (solo CR). */
  totalesVentasCr: Record<string, number> | null = null;
  totalesComprasCr: Record<string, number> | null = null;
  years: number[] = [];
  sucursales: any[] = [];
  loading = false;
  downloading = false;
  filtros: any = {};
  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private feCrUbic: FeCrUbicacionService
  ) {}

  esCostaRicaFe(): boolean {
    return this.feCrUbic.esCostaRicaFe();
  }

  ngOnInit() {
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1;
    for (let i = 0; i <= 10; i++) {
      this.years.push(currentYear - i);
    }
    this.filtros.id_sucursal = '';
    this.filtros.anio = currentYear;
    this.filtros.mes = currentMonth;
    this.setTime();

    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
      },
      (error) => {
        this.alertService.error(error);
      }
    );
    this.loadAll();
  }

  setTime() {
    this.filtros.inicio = moment([this.filtros.anio, this.filtros.mes - 1]).startOf('month').format('YYYY-MM-DD');
    this.filtros.fin = moment([this.filtros.anio, this.filtros.mes - 1]).endOf('month').format('YYYY-MM-DD');
  }

  loadAll() {
    this.loading = true;
    const cr = this.esCostaRicaFe();

    if (this.activoSeccion === 'ventas') {
      if (cr) {
        this.apiService.getAll('libro-iva/cr/reporte-detalle-iva-ventas', this.filtros).subscribe(
          (data: any) => {
            this.ventas = data?.filas ?? [];
            this.totalesVentasCr = data?.totales ?? null;
            this.loading = false;
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
      } else {
        this.totalesVentasCr = null;
        this.apiService.getAll('libro-iva/consumidores', this.filtros).subscribe(
          (data) => {
            this.ventas = data || [];
            this.loading = false;
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
      }
      return;
    }

    if (this.activoSeccion === 'compras') {
      if (cr) {
        this.apiService.getAll('libro-iva/cr/reporte-detalle-iva-compras', this.filtros).subscribe(
          (data: any) => {
            this.compras = data?.filas ?? [];
            this.totalesComprasCr = data?.totales ?? null;
            this.loading = false;
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
      } else {
        this.totalesComprasCr = null;
        this.apiService.getAll('libro-iva/compras', this.filtros).subscribe(
          (data) => {
            this.compras = data || [];
            this.loading = false;
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
      }
      return;
    }

    if (this.activoSeccion === 'retenciones') {
      this.apiService.getAll('libro-iva/retenciones', this.filtros).subscribe(
        (data) => {
          this.retenciones = data || [];
          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
      return;
    }

    this.loading = false;
  }

  onFiltroChange() {
    this.setTime();
    this.loadAll();
  }

  openModal(template: TemplateRef<any>) {
    this.modalRef = this.modalService.show(template);
  }

  private manejarErrorDescarga(error: any) {
    if (error?.error instanceof Blob) {
      error.error.text().then((text: string) => {
        try {
          const errorJson = JSON.parse(text);
          this.alertService.error({ status: error.status || 409, error: { message: errorJson.message } });
        } catch {
          this.alertService.error({ status: error.status || 409, error: { message: text } });
        }
      });
    } else {
      this.alertService.error(error);
    }
    this.downloading = false;
  }

  private descargarBlob(data: Blob, mime: string, filename: string) {
    const blob = new Blob([data], { type: mime });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    this.downloading = false;
  }

  descargarVentasExcel() {
    this.downloading = true;
    const path = this.esCostaRicaFe()
      ? 'libro-iva/cr/reporte-detalle-iva-ventas/descargar-excel'
      : 'libro-iva/consumidores/descargar-libro';
    const name = this.esCostaRicaFe() ? 'Reporte_Detalle_IVA.xlsx' : 'Libro-ventas.xlsx';
    this.apiService.export(path, this.filtros).subscribe(
      (data: Blob) => this.descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', name),
      (error) => this.manejarErrorDescarga(error)
    );
  }

  descargarVentasCsv() {
    if (!this.esCostaRicaFe()) {
      return;
    }
    this.downloading = true;
    this.apiService.export('libro-iva/cr/reporte-detalle-iva-ventas/descargar-csv', this.filtros).subscribe(
      (data: Blob) => this.descargarBlob(data, 'text/csv;charset=utf-8', 'Reporte_Detalle_IVA.csv'),
      (error) => this.manejarErrorDescarga(error)
    );
  }

  descargarVentasPDF() {
    if (this.esCostaRicaFe()) {
      return;
    }
    this.downloading = true;
    const token = this.apiService.auth_token();
    const params = new URLSearchParams(this.filtros as any).toString();
    const url = `${this.apiService.baseUrl}/api/libro-iva/consumidores?${params}&formato=pdf&token=${token}`;
    window.open(url, '_blank');
    this.downloading = false;
  }

  descargarComprasExcel() {
    this.downloading = true;
    const path = this.esCostaRicaFe()
      ? 'libro-iva/cr/reporte-detalle-iva-compras/descargar-excel'
      : 'libro-iva/compras/descargar-libro';
    const name = this.esCostaRicaFe() ? 'Reporte_Detalle_IVA_Compras.xlsx' : 'Libro-compras.xlsx';
    this.apiService.export(path, this.filtros).subscribe(
      (data: Blob) => this.descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', name),
      (error) => this.manejarErrorDescarga(error)
    );
  }

  descargarComprasCsv() {
    if (!this.esCostaRicaFe()) {
      return;
    }
    this.downloading = true;
    this.apiService.export('libro-iva/cr/reporte-detalle-iva-compras/descargar-csv', this.filtros).subscribe(
      (data: Blob) => this.descargarBlob(data, 'text/csv;charset=utf-8', 'Reporte_Detalle_IVA_Compras.csv'),
      (error) => this.manejarErrorDescarga(error)
    );
  }

  descargarComprasPDF() {
    if (this.esCostaRicaFe()) {
      return;
    }
    this.downloading = true;
    const token = this.apiService.auth_token();
    const params = new URLSearchParams(this.filtros as any).toString();
    const url = `${this.apiService.baseUrl}/api/libro-iva/compras?${params}&formato=pdf&token=${token}`;
    window.open(url, '_blank');
    this.downloading = false;
  }

  descargarRetencionesExcel() {
    this.downloading = true;
    this.apiService.export('libro-iva/retencion1/descargar-libro', this.filtros).subscribe(
      (data: Blob) => this.descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Libro-retenciones.xlsx'),
      (error) => this.manejarErrorDescarga(error)
    );
  }
}
