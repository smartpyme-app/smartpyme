import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router } from '@angular/router';
import { LibroIvaPaisService } from '@views/contabilidad/libro-iva-shared/libro-iva-pais.service';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { filtrarImpuestosTurismo } from '@utils/impuestos-turismo.util';

import * as moment from 'moment';

@Component({
    selector: 'app-impuesto-turismo',
    templateUrl: './impuesto-turismo.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ImpuestoTurismoComponent extends BaseModalComponent implements OnInit {

    public filas: any[] = [];
    public totalMontoTurismo = 0;
    public impuestosTurismo: any[] = [];
    public years: any[] = [];
    public sucursales: any[] = [];
    public override loading: boolean = false;
    public downloading: boolean = false;
    public filtros: any = {};

    constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private router: Router,
        private cdr: ChangeDetectorRef,
        private libroIvaPais: LibroIvaPaisService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        if (this.libroIvaPais.redirigirSiPaisIncorrecto('sv', this.router)) {
            return;
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

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe(sucursales => {
            this.sucursales = sucursales;
            this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('impuestos')
          .pipe(this.untilDestroyed())
          .subscribe(impuestos => {
            this.impuestosTurismo = filtrarImpuestosTurismo(impuestos);
            this.cdr.markForCheck();
        }, error => { this.alertService.error(error); });

        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('libro-iva-sv/impuesto-turismo', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe(data => {
            this.filas = data?.filas || [];
            this.totalMontoTurismo = Number(data?.total_monto_turismo || 0);
            this.loading = false;
            this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public setTime() {
        this.filtros.inicio = moment([this.filtros.anio, this.filtros.mes - 1]).startOf('month').format('YYYY-MM-DD');
        this.filtros.fin = moment([this.filtros.anio, this.filtros.mes - 1]).endOf('month').format('YYYY-MM-DD');
        this.loadAll();
        this.cdr.markForCheck();
    }

    public descargarLibro() {
        this.downloading = true;
        this.apiService.export('libro-iva-sv/impuesto-turismo/descargar-libro', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data: Blob) => {
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
            this.cdr.markForCheck();
          }, (error) => { this.manejarErrorDescarga(error); this.cdr.markForCheck(); }
        );
    }

    private manejarErrorDescarga(error: any): void {
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
}
