import { Component, OnInit } from '@angular/core';
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

interface LibroComprasHnFila {
  no: number;
  fecha_emision: string;
  numero_documento: string;
  nrc: string;
  nit_o_dui: string;
  nombre_proveedor: string;
  exentas_internas: number;
  exentas_internaciones: number;
  exentas_importaciones: number;
  gravadas_internas: number;
  gravadas_internaciones: number;
  gravadas_importaciones: number;
  credito_fiscal: number;
  fovial: number;
  cotrans: number;
  cesc: number;
  anticipo_iva_percibido: number;
  total: number;
  retencion_terceros: number;
  compras_sujetos_excluidos: number;
}

interface LibroComprasHnTotales {
  exentas_internas: number;
  exentas_internaciones: number;
  exentas_importaciones: number;
  gravadas_internas: number;
  gravadas_internaciones: number;
  gravadas_importaciones: number;
  credito_fiscal: number;
  fovial: number;
  cotrans: number;
  cesc: number;
  anticipo_iva_percibido: number;
  total: number;
  retencion_terceros: number;
  compras_sujetos_excluidos: number;
}

interface LibroComprasHnResponse {
  filas: LibroComprasHnFila[];
  totales: LibroComprasHnTotales;
}

const TOTALES_VACIOS: LibroComprasHnTotales = {
  exentas_internas: 0,
  exentas_internaciones: 0,
  exentas_importaciones: 0,
  gravadas_internas: 0,
  gravadas_internaciones: 0,
  gravadas_importaciones: 0,
  credito_fiscal: 0,
  fovial: 0,
  cotrans: 0,
  cesc: 0,
  anticipo_iva_percibido: 0,
  total: 0,
  retencion_terceros: 0,
  compras_sujetos_excluidos: 0,
};

@Component({
  selector: 'app-libro-iva-hd-compras',
  standalone: true,
  imports: [CommonModule, LibroIvaHdNavComponent, LibroIvaPeriodoFiltrosComponent, TranslatePipe, CurrencyPipe],
  templateUrl: './libro-iva-hd-compras.component.html',
})
export class LibroIvaHdComprasComponent implements OnInit {
  filas: LibroComprasHnFila[] = [];
  totales: LibroComprasHnTotales = { ...TOTALES_VACIOS };
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

  loadData(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.loading = true;
    this.apiService.getAll('libro-iva-hd/compras', this.filtros).subscribe(
      (data: LibroComprasHnResponse) => {
        this.filas = data?.filas ?? [];
        this.totales = data?.totales ? { ...TOTALES_VACIOS, ...data.totales } : { ...TOTALES_VACIOS };
        this.loading = false;
      },
      (error) => {
        this.filas = [];
        this.totales = { ...TOTALES_VACIOS };
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  descargarExcel(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.downloading = true;
    this.apiService.export('libro-iva-hd/compras/descargar-libro', this.filtros).subscribe(
      (data: Blob) => {
        descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Libro-compras.xlsx');
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
    const url = `${this.apiService.baseUrl}/api/libro-iva-hd/compras?${query}&formato=pdf&token=${token}`;
    window.open(url, '_blank');
  }
}
