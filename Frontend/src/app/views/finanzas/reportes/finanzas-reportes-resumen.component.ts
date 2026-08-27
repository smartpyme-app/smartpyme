import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { LibroIvaPeriodoFiltrosComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-periodo-filtros.component';
import { LibroIvaResumenPanelComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-resumen-panel.component';
import { LibroIvaResumenDescargasComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-resumen-descargas.component';
import {
  aplicarPrimeraSucursalLibroIva,
  aplicarRangoMesLibroIva,
  crearAniosLibroIva,
  crearFiltrosLibroIvaIniciales,
} from '@views/contabilidad/libro-iva-shared/libro-iva-filtros.util';
import { FinanzasReportesNavComponent } from './finanzas-reportes-nav.component';

@Component({
  selector: 'app-finanzas-reportes-resumen',
  standalone: true,
  imports: [
    CommonModule,
    FinanzasReportesNavComponent,
    LibroIvaPeriodoFiltrosComponent,
    LibroIvaResumenPanelComponent,
    LibroIvaResumenDescargasComponent,
  ],
  templateUrl: './finanzas-reportes-resumen.component.html',
})
export class FinanzasReportesResumenComponent implements OnInit {
  fiscalResumen: unknown = null;
  years: number[] = [];
  sucursales: unknown[] = [];
  loading = false;
  filtros: Record<string, unknown> = {};

  constructor(
    public apiService: ApiService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    this.years = crearAniosLibroIva();
    this.filtros = crearFiltrosLibroIvaIniciales();
    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
        aplicarPrimeraSucursalLibroIva(this.filtros, sucursales as Array<{ id?: unknown }>);
        this.loadData();
      },
      (error) => {
        this.alertService.error(error);
        this.loadData();
      }
    );
  }

  loadData(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.loading = true;
    this.apiService.getAll('libro-iva/resumen-fiscal', this.filtros).subscribe(
      (data) => {
        this.fiscalResumen = data;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.fiscalResumen = null;
        this.loading = false;
      }
    );
  }
}
