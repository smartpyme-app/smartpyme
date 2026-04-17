import { Component, OnInit, TemplateRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

import * as moment from 'moment';

@Component({
    selector: 'app-libro-anulados',
    templateUrl: './libro-anulados.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class LibroAnuladosComponent extends BaseModalComponent implements OnInit {

    public ivas:any[] = [];
    public years:any[] = [];
    public sucursales:any[] = [];
    public override loading:boolean = false;
    public downloading:boolean = false;
    public filtros:any = {};
    modalRef!: BsModalRef;
    public tipoDescarga: string = '';

    constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        const currentYear = new Date().getFullYear(); // Obtener el año actual
        const currentMonth = new Date().getMonth() + 1;
        // Crear un array con el año actual y los 10 años anteriores
        for (let i = 0; i <= 10; i++) {
          this.years.push(currentYear - i);
        }

        this.filtros.id_sucursal = '';
        this.filtros.tipo_documento = 'Crédito fiscal';
        this.filtros.anio = currentYear;
        this.filtros.mes = currentMonth;
        this.filtros.time = 'day';

        this.setTime();

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe(sucursales => {
            this.sucursales = sucursales;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

        this.loadAll();
    }

    /** Solo para El Salvador: opciones de descarga ZIP y CSV (declaración MH) */
    get isElSalvador(): boolean {
        return this.apiService.auth_user()?.empresa?.pais === 'El Salvador';
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('libro-iva/anulados', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe(ivas => {
            this.ivas = ivas;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

    public setTime() {
        // this.filtros.time = { this.filtros.anio, this.filtros.mes }; // Guardamos el mes y año en el filtro
        this.filtros.inicio = moment([this.filtros.anio, this.filtros.mes - 1]).startOf('month').format('YYYY-MM-DD');
        this.filtros.fin = moment([this.filtros.anio, this.filtros.mes - 1]).endOf('month').format('YYYY-MM-DD');
        this.loadAll();
        this.cdr.markForCheck();
    }

    get faltaNombre(): boolean {
        return this.ivas.some((item:any) => !item.nit_nrc);
    }

    public override openModal(template: TemplateRef<any>, config?: any) {
        super.openModal(template, config);
    }

    public openDescargasModal(template: TemplateRef<any>): void {
        this.tipoDescarga = '';
        this.modalRef = this.modalService.show(template, {
            class: 'modal-md',
            backdrop: true,
            ignoreBackdropClick: false,
        });
    }

    public cerrarModalDescargas(): void {
        this.modalRef?.hide();
        this.tipoDescarga = '';
    }

    public ejecutarDescargaSeleccionada(): void {
        if (!this.tipoDescarga) {
            this.alertService.warning('Seleccione un tipo', 'Elija una opción en el listado.');
            return;
        }
        switch (this.tipoDescarga) {
            case 'libro_excel':
                this.descargarLibro();
                break;
            case 'anexo_csv':
                this.descargarAnexo();
                break;
            default:
                this.alertService.warning('Opción no válida', 'Seleccione otra opción.');
                return;
        }
        this.modalRef?.hide();
        this.tipoDescarga = '';
    }

    private manejarErrorDescarga(error: any): void {
        // Si el error viene como Blob (JSON convertido a Blob), leerlo y mostrar el mensaje
        if (error.error instanceof Blob) {
            error.error.text().then((text: string) => {
                try {
                    const errorJson = JSON.parse(text);
                    this.alertService.error({ status: error.status || 409, error: { message: errorJson.message } });
                } catch (e) {
                    this.alertService.error({ status: error.status || 409, error: { message: text } });
                }
            });
        } else {
            this.alertService.error(error);
        }
        this.downloading = false;
        this.cdr.markForCheck();
    }

    public descargarLibro(){
        this.downloading = true;
        this.apiService.export('libro-iva/anulados/descargar-libro', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Libro de anulados.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
          }, (error) => { this.manejarErrorDescarga(error); this.cdr.markForCheck(); }
        );
    }

    public descargarAnexo() {
        this.downloading = true;
        this.apiService.export('libro-iva/anulados/descargar-anexo', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'text/csv;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Anexo-anulados.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
        }, (error) => {
            this.manejarErrorDescarga(error);
            this.cdr.markForCheck();
        });
    }

}
