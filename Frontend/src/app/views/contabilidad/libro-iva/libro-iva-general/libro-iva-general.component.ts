import { Component, OnInit, TemplateRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import {
  empresaTieneImpuestoTurismo,
  filtrarImpuestosTurismo,
} from '@utils/impuestos-turismo.util';
import * as moment from 'moment';

/**
 * Libros de IVA - Vista general para países distintos de El Salvador.
 * Muestra: Ventas, Compras y Retenciones (+ turismo solo si la empresa lo tiene).
 * También sirve la ruta dedicada de turismo (El Salvador) con data.soloTurismo.
 */
@Component({
  selector: 'app-libro-iva-general',
  templateUrl: './libro-iva-general.component.html',
})
export class LibroIvaGeneralComponent implements OnInit {
  activoSeccion: 'ventas' | 'compras' | 'retenciones' | 'turismo' = 'ventas';
  soloTurismo = false;
  tieneImpuestoTurismo = false;
  ventas: any[] = [];
  compras: any[] = [];
  retenciones: any[] = [];
  impuestoTurismo: any[] = [];
  totalImpuestoTurismo = 0;
  impuestosTurismo: any[] = [];
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
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit() {
    this.soloTurismo = this.route.snapshot.data['soloTurismo'] === true;
    if (this.soloTurismo) {
      this.activoSeccion = 'turismo';
    }

    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1;
    for (let i = 0; i <= 10; i++) {
      this.years.push(currentYear - i);
    }
    this.filtros.id_sucursal = '';
    this.filtros.id_impuesto = '';
    this.filtros.anio = currentYear;
    this.filtros.mes = currentMonth;
    this.setTime();

    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => { this.sucursales = sucursales; },
      (error) => { this.alertService.error(error); }
    );
    this.apiService.getAll('impuestos').subscribe(
      (impuestos) => {
        this.impuestosTurismo = filtrarImpuestosTurismo(impuestos);
        this.tieneImpuestoTurismo = empresaTieneImpuestoTurismo(impuestos);

        if (this.soloTurismo && !this.tieneImpuestoTurismo) {
          const pais = this.apiService.auth_user()?.empresa?.pais ?? '';
          this.router.navigate([
            pais === 'El Salvador' ? '/libro-iva/contribuyentes' : '/libro-iva/general',
          ]);
          return;
        }

        this.loadAll();
      },
      (error) => {
        this.alertService.error(error);
        this.loadAll();
      }
    );
  }

  set activarSeccion(seccion: 'ventas' | 'compras' | 'retenciones' | 'turismo') {
    this.activoSeccion = seccion;
    this.loadAll();
  }

  setTime() {
    this.filtros.inicio = moment([this.filtros.anio, this.filtros.mes - 1]).startOf('month').format('YYYY-MM-DD');
    this.filtros.fin = moment([this.filtros.anio, this.filtros.mes - 1]).endOf('month').format('YYYY-MM-DD');
  }

  loadAll() {
    this.loading = true;
    if (this.activoSeccion === 'ventas') {
      this.apiService.getAll('libro-iva/consumidores', this.filtros).subscribe(
        (data) => { this.ventas = data || []; this.loading = false; },
        (error) => { this.alertService.error(error); this.loading = false; }
      );
    } else if (this.activoSeccion === 'compras') {
      this.apiService.getAll('libro-iva/compras', this.filtros).subscribe(
        (data) => { this.compras = data || []; this.loading = false; },
        (error) => { this.alertService.error(error); this.loading = false; }
      );
    } else if (this.activoSeccion === 'retenciones') {
      this.apiService.getAll('libro-iva/retenciones', this.filtros).subscribe(
        (data) => { this.retenciones = data || []; this.loading = false; },
        (error) => { this.alertService.error(error); this.loading = false; }
      );
    } else if (this.activoSeccion === 'turismo') {
      this.apiService.getAll('libro-iva/impuesto-turismo', this.filtros).subscribe(
        (data) => {
          this.impuestoTurismo = data?.filas || [];
          this.totalImpuestoTurismo = Number(data?.total_monto_turismo || 0);
          this.loading = false;
        },
        (error) => { this.alertService.error(error); this.loading = false; }
      );
    } else {
      this.loading = false;
    }
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

  descargarVentasExcel() {
    this.downloading = true;
    this.apiService.export('libro-iva/consumidores/descargar-libro', this.filtros).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'Libro-ventas.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      (error) => this.manejarErrorDescarga(error)
    );
  }

  descargarVentasPDF() {
    this.downloading = true;
    const token = this.apiService.auth_token();
    const params = new URLSearchParams(this.filtros as any).toString();
    const url = `${this.apiService.baseUrl}/api/libro-iva/consumidores?${params}&formato=pdf&token=${token}`;
    window.open(url, '_blank');
    this.downloading = false;
  }

  descargarComprasExcel() {
    this.downloading = true;
    this.apiService.export('libro-iva/compras/descargar-libro', this.filtros).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'Libro-compras.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      (error) => this.manejarErrorDescarga(error)
    );
  }

  descargarComprasPDF() {
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
      (data: Blob) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'Libro-retenciones.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      (error) => this.manejarErrorDescarga(error)
    );
  }

  descargarImpuestoTurismoExcel() {
    this.downloading = true;
    this.apiService.export('libro-iva/impuesto-turismo/descargar-libro', this.filtros).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'Libro-impuesto-turismo-5.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      (error) => this.manejarErrorDescarga(error)
    );
  }

  get simboloMoneda(): string {
    return this.apiService.auth_user()?.empresa?.currency?.currency_symbol || '$';
  }
}
