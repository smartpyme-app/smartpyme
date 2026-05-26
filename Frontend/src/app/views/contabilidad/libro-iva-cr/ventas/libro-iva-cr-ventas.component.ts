import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { LibroIvaCrDetalleTableComponent } from '@views/contabilidad/libro-iva-cr/shared/libro-iva-cr-detalle-table.component';
import { LibroIvaCrNavComponent } from '@views/contabilidad/libro-iva-cr/libro-iva-cr-nav.component';
import { LibroIvaPeriodoFiltrosComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-periodo-filtros.component';
import { LibroIvaPaisService } from '@views/contabilidad/libro-iva-shared/libro-iva-pais.service';
import {
  aplicarRangoMesLibroIva,
  crearAniosLibroIva,
  crearFiltrosLibroIvaIniciales,
} from '@views/contabilidad/libro-iva-shared/libro-iva-filtros.util';
import { descargarBlob, manejarErrorDescargaLibroIva } from '@views/contabilidad/libro-iva-shared/libro-iva-descarga.util';

@Component({
  selector: 'app-libro-iva-cr-ventas',
  standalone: true,
  imports: [CommonModule, LibroIvaCrNavComponent, LibroIvaPeriodoFiltrosComponent, LibroIvaCrDetalleTableComponent],
  templateUrl: './libro-iva-cr-ventas.component.html',
})
export class LibroIvaCrVentasComponent implements OnInit {
  ventas: Record<string, unknown>[] = [];
  totales: Record<string, number> | null = null;
  years: number[] = [];
  sucursales: unknown[] = [];
  loading = false;
  downloading = false;
  filtros: Record<string, unknown> = {};

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private libroIvaPais: LibroIvaPaisService
  ) {}

  ngOnInit(): void {
    if (this.libroIvaPais.redirigirSiPaisIncorrecto('cr', this.router)) {
      return;
    }
    this.years = crearAniosLibroIva();
    this.filtros = crearFiltrosLibroIvaIniciales();
    this.loadData();
    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
      },
      (error) => this.alertService.error(error)
    );
  }

  loadData(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.loading = true;
    this.apiService.getAll('libro-iva-cr/ventas', this.filtros).subscribe(
      (data: { filas?: Record<string, unknown>[]; totales?: Record<string, number> }) => {
        this.ventas = data?.filas ?? [];
        this.totales = data?.totales ?? null;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  descargarExcel(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.downloading = true;
    this.apiService.export('libro-iva-cr/ventas/descargar-excel', this.filtros).subscribe(
      (data: Blob) => {
        descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Reporte_Detalle_IVA.xlsx');
        this.downloading = false;
      },
      (error) => {
        manejarErrorDescargaLibroIva(error, this.alertService);
        this.downloading = false;
      }
    );
  }

  descargarCsv(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.downloading = true;
    this.apiService.export('libro-iva-cr/ventas/descargar-csv', this.filtros).subscribe(
      (data: Blob) => {
        descargarBlob(data, 'text/csv;charset=utf-8', 'Reporte_Detalle_IVA.csv');
        this.downloading = false;
      },
      (error) => {
        manejarErrorDescargaLibroIva(error, this.alertService);
        this.downloading = false;
      }
    );
  }
}
