import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { LibroIvaCrNavComponent } from '@views/contabilidad/libro-iva-cr/libro-iva-cr-nav.component';
import { LibroIvaPeriodoFiltrosComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-periodo-filtros.component';
import { LibroIvaResumenPanelComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-resumen-panel.component';
import { LibroIvaPaisService } from '@views/contabilidad/libro-iva-shared/libro-iva-pais.service';
import {
  aplicarRangoMesLibroIva,
  crearAniosLibroIva,
  crearFiltrosLibroIvaIniciales,
} from '@views/contabilidad/libro-iva-shared/libro-iva-filtros.util';
import { LibroIvaResumenDescargasComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-resumen-descargas.component';
import { TranslatePipe } from '@ngx-translate/core';

@Component({
  selector: 'app-libro-iva-cr-resumen',
  standalone: true,
  imports: [
    CommonModule,
    LibroIvaCrNavComponent,
    LibroIvaPeriodoFiltrosComponent,
    LibroIvaResumenPanelComponent,
    LibroIvaResumenDescargasComponent,
    TranslatePipe,
  ],
  templateUrl: './libro-iva-cr-resumen.component.html',
})
export class LibroIvaCrResumenComponent implements OnInit {
  fiscalResumen: unknown = null;
  years: number[] = [];
  sucursales: unknown[] = [];
  loading = false;
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
