import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SumPipe } from '@pipes/sum.pipe';

import * as moment from 'moment';

@Component({
    selector: 'app-consumidor-final',
    templateUrl: './consumidor-final.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, SumPipe],
    
})

export class ConsumidorFinalComponent implements OnInit {

	public ivas:any[] = [];
    public years:any[] = [];
    public sucursales:any[] = [];
    public loading:boolean = false;
    public downloading:boolean = false;
    public filtros:any = {};
    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

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

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('libro-iva/consumidores', this.filtros).subscribe(ivas => {
            this.ivas = ivas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setTime() {
        // this.filtros.time = { this.filtros.anio, this.filtros.mes }; // Guardamos el mes y año en el filtro
        this.filtros.inicio = moment([this.filtros.anio, this.filtros.mes - 1]).startOf('month').format('YYYY-MM-DD');
        this.filtros.fin = moment([this.filtros.anio, this.filtros.mes - 1]).endOf('month').format('YYYY-MM-DD');
        this.loadAll();
    }

    public openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public descargarLibro(){
        this.downloading = true;
        this.apiService.export('libro-iva/consumidores/descargar-libro', this.filtros).subscribe((data:Blob) => {
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
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    public descargarAnexo() {
        this.downloading = true;
        this.apiService.export('libro-iva/consumidores/descargar-anexo', this.filtros).subscribe((data: Blob) => {
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
        }, (error) => {
            this.alertService.error(error);
            this.downloading = false;
        });
    }

    public descargarDTEConsumidorFinal(): void {
        this.downloading = true;
        let typeDTE : string = '01';
        this.filtros.typeDTE = typeDTE;
        this.apiService.export('libro-iva/consumidores/descargar-dttes', this.filtros).subscribe(
          (data: Blob) => {
            // Si es texto plano, es un mensaje de error
            if (data.type === 'text/plain') {
              data.text().then((errorMessage: string) => {
                this.alertService.error(errorMessage);
              });
              this.downloading = false;
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
          },
          (error: any) => {
            // Para errores HTTP que no devuelven un Blob
            if (error.error instanceof Blob && error.error.type === 'text/plain') {
              error.error.text().then((errorMessage: string) => {
                this.alertService.error(errorMessage);
              });
            } else {
              this.alertService.error(error.message || 'Error desconocido');
            }
            this.downloading = false;
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
