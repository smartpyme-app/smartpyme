import { Component, OnInit, TemplateRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { SumPipe } from '@pipes/sum.pipe';

import * as moment from 'moment';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-consumidor-final',
    templateUrl: './consumidor-final.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, SumPipe, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class ConsumidorFinalComponent extends BaseModalComponent implements OnInit {

	public ivas:any[] = [];
    public years:any[] = [];
    public sucursales:any[] = [];
    public override loading:boolean = false;
    public downloading:boolean = false;
    public filtros:any = {};

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
        this.apiService.getAll('libro-iva/consumidores', this.filtros)
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

    public override openModal(template: TemplateRef<any>, config?: any) {
        super.openModal(template, config);
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
        this.apiService.export('libro-iva/consumidores/descargar-libro', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Libro-consumidores.xlsx';
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
        this.apiService.export('libro-iva/consumidores/descargar-anexo', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'text/csv;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Anexo-consumidores.csv';
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

    public descargarDTEConsumidorFinal(): void {
        this.downloading = true;
        let typeDTE : string = '01';
        this.filtros.typeDTE = typeDTE;
        this.apiService.export('libro-iva/consumidores/descargar-dttes', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe(
          (data: Blob) => {
            // Si es texto plano, es un mensaje de error
            if (data.type === 'text/plain') {
              data.text().then((errorMessage: string) => {
                this.alertService.error(errorMessage);
                this.cdr.markForCheck();
              });
              this.downloading = false;
              this.cdr.markForCheck();
              return;
            }

            // Si no es texto plano, es un archivo ZIP
            const url = window.URL.createObjectURL(data);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'DTEs_Export_' + new Date().toISOString().slice(0, 10) + '.zip';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
          },
          (error: any) => {
            // Para errores HTTP que no devuelven un Blob
            if (error.error instanceof Blob && error.error.type === 'text/plain') {
              error.error.text().then((errorMessage: string) => {
                this.alertService.error(errorMessage);
                this.cdr.markForCheck();
              });
            } else {
              this.alertService.error(error.message || 'Error desconocido');
            }
            this.downloading = false;
            this.cdr.markForCheck();
          }
        );
      }

      public descargarLibroPDF(): void {
        this.filtros.formato = 'pdf';
        const filtros = new URLSearchParams(this.filtros).toString();
        const token = this.apiService.auth_token();

        const url = `${this.apiService.baseUrl}/api/libro-iva/consumidores?${filtros}&token=${token}`;
        window.open(url, '_blank');
      }

}
