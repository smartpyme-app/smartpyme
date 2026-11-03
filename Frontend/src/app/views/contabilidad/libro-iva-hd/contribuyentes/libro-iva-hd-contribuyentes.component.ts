import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { LibroIvaHdNavComponent } from '@views/contabilidad/libro-iva-hd/libro-iva-hd-nav.component';
import { LibroIvaPeriodoFiltrosComponent } from '@views/contabilidad/libro-iva-shared/libro-iva-periodo-filtros.component';
import { LibroIvaPaisService } from '@views/contabilidad/libro-iva-shared/libro-iva-pais.service';
import {
  aplicarPrimeraSucursalLibroIva,
  aplicarRangoMesLibroIva,
  crearAniosLibroIva,
  crearFiltrosLibroIvaIniciales,
} from '@views/contabilidad/libro-iva-shared/libro-iva-filtros.util';
import { descargarBlob, manejarErrorDescargaLibroIva } from '@views/contabilidad/libro-iva-shared/libro-iva-descarga.util';
import { TranslatePipe } from '@ngx-translate/core';

interface LibroContribuyentesHnFila {
  no: number;
  fecha: string;
  correlativo: string;
  nrc: string;
  nombre: string;
  exentas: number;
  no_sujetas: number;
  gravadas_locales: number;
  debito_fiscal: number;
  cta_terceros: number;
  debito_cta_terceros: number;
  iva_percibido: number;
  iva_retenido: number;
  total: number;
}

interface ResumenOperacionBloque {
  gravadas: number;
  exportaciones: number;
  debito_fiscal: number;
  iva_percibido: number;
  iva_retenido: number;
}

interface LibroContribuyentesHnResumenOperaciones {
  totales_detalle: ResumenOperacionBloque;
  consumidor_final: ResumenOperacionBloque;
  contribuyentes: ResumenOperacionBloque;
  cta_terceros: ResumenOperacionBloque;
}

interface LibroContribuyentesHnResponse {
  filas: LibroContribuyentesHnFila[];
  resumen_operaciones: LibroContribuyentesHnResumenOperaciones;
}

const BLOQUE_VACIO: ResumenOperacionBloque = {
  gravadas: 0,
  exportaciones: 0,
  debito_fiscal: 0,
  iva_percibido: 0,
  iva_retenido: 0,
};

@Component({
  selector: 'app-libro-iva-hd-contribuyentes',
  standalone: true,
  imports: [CommonModule, LibroIvaHdNavComponent, LibroIvaPeriodoFiltrosComponent, TranslatePipe, CurrencyPipe],
  templateUrl: './libro-iva-hd-contribuyentes.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LibroIvaHdContribuyentesComponent implements OnInit {
  filas: LibroContribuyentesHnFila[] = [];
  totalesFilas = {
    exentas: 0,
    no_sujetas: 0,
    gravadas_locales: 0,
    debito_fiscal: 0,
    cta_terceros: 0,
    debito_cta_terceros: 0,
    iva_percibido: 0,
    iva_retenido: 0,
    total: 0,
  };
  resumenOperaciones: LibroContribuyentesHnResumenOperaciones = {
    totales_detalle: { ...BLOQUE_VACIO },
    consumidor_final: { ...BLOQUE_VACIO },
    contribuyentes: { ...BLOQUE_VACIO },
    cta_terceros: { ...BLOQUE_VACIO },
  };
  years: number[] = [];
  sucursales: unknown[] = [];
  loading = false;
  downloading = false;
  filtros: Record<string, unknown> = {};

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private libroIvaPais: LibroIvaPaisService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    if (this.libroIvaPais.redirigirSiPaisIncorrecto('hd', this.router)) {
      return;
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

  private recalcularTotalesFilas(): void {
    const t = {
      exentas: 0,
      no_sujetas: 0,
      gravadas_locales: 0,
      debito_fiscal: 0,
      cta_terceros: 0,
      debito_cta_terceros: 0,
      iva_percibido: 0,
      iva_retenido: 0,
      total: 0,
    };
    for (const r of this.filas) {
      t.exentas += r.exentas || 0;
      t.no_sujetas += r.no_sujetas || 0;
      t.gravadas_locales += r.gravadas_locales || 0;
      t.debito_fiscal += r.debito_fiscal || 0;
      t.cta_terceros += r.cta_terceros || 0;
      t.debito_cta_terceros += r.debito_cta_terceros || 0;
      t.iva_percibido += r.iva_percibido || 0;
      t.iva_retenido += r.iva_retenido || 0;
      t.total += r.total || 0;
    }
    this.totalesFilas = t;
  }

  loadData(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.loading = true;
    this.apiService.getAll('libro-iva-hd/contribuyentes', this.filtros).subscribe(
      (data: LibroContribuyentesHnResponse) => {
        this.filas = data?.filas ?? [];
        this.recalcularTotalesFilas();
        this.resumenOperaciones = this.normalizarResumen(data?.resumen_operaciones);
        this.loading = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.filas = [];
        this.recalcularTotalesFilas();
        this.resumenOperaciones = {
          totales_detalle: { ...BLOQUE_VACIO },
          consumidor_final: { ...BLOQUE_VACIO },
          contribuyentes: { ...BLOQUE_VACIO },
          cta_terceros: { ...BLOQUE_VACIO },
        };
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      }
    );
  }

  private normalizarResumen(
    resumen?: LibroContribuyentesHnResumenOperaciones
  ): LibroContribuyentesHnResumenOperaciones {
    if (!resumen) {
      return {
        totales_detalle: { ...BLOQUE_VACIO },
        consumidor_final: { ...BLOQUE_VACIO },
        contribuyentes: { ...BLOQUE_VACIO },
        cta_terceros: { ...BLOQUE_VACIO },
      };
    }
    return {
      totales_detalle: { ...BLOQUE_VACIO, ...resumen.totales_detalle },
      consumidor_final: { ...BLOQUE_VACIO, ...resumen.consumidor_final },
      contribuyentes: { ...BLOQUE_VACIO, ...resumen.contribuyentes },
      cta_terceros: { ...BLOQUE_VACIO, ...resumen.cta_terceros },
    };
  }

  descargarExcel(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.downloading = true;
    this.apiService.export('libro-iva-hd/contribuyentes/descargar-libro', this.filtros).subscribe(
      (data: Blob) => {
        descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Libro-contribuyentes.xlsx');
        this.downloading = false;
      },
      (error) => {
        manejarErrorDescargaLibroIva(error, this.alertService);
        this.downloading = false;
      }
    );
  }

  descargarPdf(): void {
    aplicarRangoMesLibroIva(this.filtros);
    const token = this.apiService.auth_token();
    const query = new URLSearchParams(this.filtros as Record<string, string>).toString();
    const url = `${this.apiService.baseUrl}/api/libro-iva-hd/contribuyentes?${query}&formato=pdf&token=${token}`;
    window.open(url, '_blank');
  }
}
