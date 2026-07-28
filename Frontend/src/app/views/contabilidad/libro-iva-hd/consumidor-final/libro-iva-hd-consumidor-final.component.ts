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

interface LibroConsumidoresHnFila {
  no: number;
  fecha: string;
  factura_no: string;
  cai_no: string;
  maquina_registradora: string;
  exentas: number;
  exoneradas: number;
  gravadas_15: number;
  gravadas_18: number;
  total_ventas: number;
  cuenta_terceros: number;
}

interface LibroConsumidoresHnResumen {
  total_exentas: number;
  total_exoneradas: number;
  netas_15: number;
  netas_18: number;
  debito_fiscal: number;
  credito_fiscal: number;
}

interface LibroConsumidoresHnResponse {
  filas: LibroConsumidoresHnFila[];
  resumen: LibroConsumidoresHnResumen;
}

const RESUMEN_VACIO: LibroConsumidoresHnResumen = {
  total_exentas: 0,
  total_exoneradas: 0,
  netas_15: 0,
  netas_18: 0,
  debito_fiscal: 0,
  credito_fiscal: 0,
};

@Component({
  selector: 'app-libro-iva-hd-consumidor-final',
  standalone: true,
  imports: [CommonModule, LibroIvaHdNavComponent, LibroIvaPeriodoFiltrosComponent, TranslatePipe, CurrencyPipe],
  templateUrl: './libro-iva-hd-consumidor-final.component.html',
})
export class LibroIvaHdConsumidorFinalComponent implements OnInit {
  filas: LibroConsumidoresHnFila[] = [];
  resumen: LibroConsumidoresHnResumen = { ...RESUMEN_VACIO };
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

  get totalExentas(): number {
    return this.filas.reduce((s, r) => s + (r.exentas || 0), 0);
  }

  get totalExoneradas(): number {
    return this.filas.reduce((s, r) => s + (r.exoneradas || 0), 0);
  }

  get totalGravadas15(): number {
    return this.filas.reduce((s, r) => s + (r.gravadas_15 || 0), 0);
  }

  get totalGravadas18(): number {
    return this.filas.reduce((s, r) => s + (r.gravadas_18 || 0), 0);
  }

  get totalVentas(): number {
    return this.filas.reduce((s, r) => s + (r.total_ventas || 0), 0);
  }

  get totalCuentaTerceros(): number {
    return this.filas.reduce((s, r) => s + (r.cuenta_terceros || 0), 0);
  }

  loadData(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.loading = true;
    this.apiService.getAll('libro-iva-hd/consumidores', this.filtros).subscribe(
      (data: LibroConsumidoresHnResponse) => {
        this.filas = data?.filas ?? [];
        this.resumen = data?.resumen ? { ...RESUMEN_VACIO, ...data.resumen } : { ...RESUMEN_VACIO };
        this.loading = false;
      },
      (error) => {
        this.filas = [];
        this.resumen = { ...RESUMEN_VACIO };
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  descargarExcel(): void {
    aplicarRangoMesLibroIva(this.filtros);
    this.downloading = true;
    this.apiService.export('libro-iva-hd/consumidores/descargar-libro', this.filtros).subscribe(
      (data: Blob) => {
        descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Libro-consumidores.xlsx');
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
    const url = `${this.apiService.baseUrl}/api/libro-iva-hd/consumidores?${query}&formato=pdf&token=${token}`;
    window.open(url, '_blank');
  }
}
