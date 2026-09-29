import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { LibroIvaCrNavComponent } from '@views/contabilidad/libro-iva-cr/libro-iva-cr-nav.component';
import { LibroIvaGeneralNavComponent } from '@views/contabilidad/libro-iva-general/libro-iva-general-nav.component';
import { LibroIvaHdNavComponent } from '@views/contabilidad/libro-iva-hd/libro-iva-hd-nav.component';
import { LibroIvaSvNavComponent } from '@views/contabilidad/libro-iva-sv/libro-iva-sv-nav.component';
import { LibroIvaPeriodoFiltrosComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-periodo-filtros.component';
import { LibroIvaPaisService, LibroIvaPaisTipo } from '@views/contabilidad/libro-iva-shared/libro-iva-pais.service';
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
    TranslatePipe,
    FinanzasReportesNavComponent,
    LibroIvaSvNavComponent,
    LibroIvaCrNavComponent,
    LibroIvaHdNavComponent,
    LibroIvaGeneralNavComponent,
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
  enLibrosFiscales = false;
  tipoLibro: LibroIvaPaisTipo = 'general';

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private libroIvaPais: LibroIvaPaisService
  ) {}

  ngOnInit(): void {
    this.enLibrosFiscales = this.route.snapshot.data['enLibrosFiscales'] === true;
    this.tipoLibro = this.libroIvaPais.tipoLibroIva();
    if (this.enLibrosFiscales) {
      const destino = this.libroIvaPais.rutaResumenLibroIva()[0];
      if (this.router.url.split('?')[0] !== destino) {
        void this.router.navigateByUrl(destino, { replaceUrl: true });
        return;
      }
    }

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
