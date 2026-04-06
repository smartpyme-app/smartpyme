import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-contribuyentes',
  templateUrl: './contribuyentes.component.html',
})

export class ContribuyentesComponent implements OnInit {

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
        //JSON en ZIP DTEs / notas: no_anulados (por defecto) | anulados
        this.filtros.estado_json = 'no_anulados';
        this.filtros.anio = currentYear;
        this.filtros.mes = currentMonth;
        this.filtros.time = 'day';
        this.setTime();


        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.loadAll();
    }

    /** Solo para El Salvador: opciones de descarga ZIP y CSV (declaración MH) */
    get isElSalvador(): boolean {
        return this.apiService.auth_user()?.empresa?.pais === 'El Salvador';
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('libro-iva/contribuyentes', this.filtros).subscribe(ivas => { 
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

    private manejarErrorDescarga(error: any): void {
        if (error.error instanceof Blob) {
            error.error.text().then((text: string) => {
                try {
                    const errorJson = JSON.parse(text);
                    const msg = errorJson.message ?? errorJson.error ?? text;
                    this.alertService.error({ status: error.status || 409, error: { error: msg } });
                } catch (e) {
                    this.alertService.error({ status: error.status || 409, error: { error: text } });
                }
            });
        } else {
            this.alertService.error(error);
        }
        this.downloading = false;
    }

    public descargarLibro(){
        this.downloading = true;
        this.apiService.export('libro-iva/contribuyentes/descargar-libro', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Libro-contribuyentes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.manejarErrorDescarga(error); }
        );
    }


    public descargarLibroRetencion(){
        this.downloading = true;
        this.apiService.export('libro-iva/retencion1/descargar-libro', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Retenciones1.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.manejarErrorDescarga(error); }
        );
    }

    public descargarAnexoRetencion() {
        this.downloading = true;
        this.apiService.export('libro-iva/retencion1/descargar-anexo', this.filtros).subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'text/csv;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Retenciones1.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
        }, (error) => {
            this.manejarErrorDescarga(error);
        });
    }

    public descargarAnexo() {
        this.downloading = true;
        this.apiService.export('libro-iva/contribuyentes/descargar-anexo', this.filtros).subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'text/csv;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Anexo-contribuyentes.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
        }, (error) => {
            this.manejarErrorDescarga(error);
        });
    }


    public descargarNotasCredito(): void {
        this.downloading = true;
        const filtros = { ...this.filtros, tipo_nota: '05' };
        this.apiService.export('libro-iva/contribuyentes/descargar-notas-credito-debito', filtros).subscribe(
            (data: Blob) => this.procesarDescargaNotas(data, 'NotasCredito'),
            (error: any) => this.manejarErrorDescarga(error)
        );
    }

    public descargarNotasDebito(): void {
        this.downloading = true;
        const filtros = { ...this.filtros, tipo_nota: '06' };
        this.apiService.export('libro-iva/contribuyentes/descargar-notas-credito-debito', filtros).subscribe(
            (data: Blob) => this.procesarDescargaNotas(data, 'NotasDebito'),
            (error: any) => this.manejarErrorDescarga(error)
        );
    }

    private procesarDescargaNotas(data: Blob, prefijo: string): void {
        if (data.type === 'text/plain') {
            data.text().then((errorMessage: string) => {
                this.alertService.error({ status: 400, error: { error: errorMessage } });
                this.downloading = false;
            });
            return;
        }
        if (data.size === 0) {
            this.alertService.error('El archivo descargado está vacío');
            this.downloading = false;
            return;
        }
        const fechaInicio = this.filtros.inicio.replace(/-/g, '');
        const fechaFin = this.filtros.fin.replace(/-/g, '');
        const sufijo = this.filtros.estado_json === 'anulados' ? '_anulados' : '';
        const filename = `${prefijo}${sufijo}_${fechaInicio}_${fechaFin}.zip`;
        const url = window.URL.createObjectURL(data);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }, 100);
        this.downloading = false;
        this.alertService.success('Éxito', 'Archivo descargado correctamente');
    }

    public descargarDTECreditoFiscal(): void {
      this.downloading = true;
      let typeDTE: string = '03';
      this.filtros.typeDTE = typeDTE;
      
      this.apiService.export('libro-iva/contribuyentes/descargar-dttes', this.filtros).subscribe(
          (data: Blob) => {
              if (data.type === 'text/plain') {
                  data.text().then((errorMessage: string) => {
                      this.alertService.error(errorMessage);
                      this.downloading = false;
                  });
                  return;
              }
              
              if (data.size === 0) {
                  this.alertService.error('El archivo descargado está vacío');
                  this.downloading = false;
                  return;
              }
              
              const fechaInicio = this.filtros.inicio.replace(/-/g, '');
              const fechaFin = this.filtros.fin.replace(/-/g, '');
              const prefijoDte = this.filtros.estado_json === 'anulados' ? 'DTEs_anulados_' : 'DTEs_';
              const filename = `${prefijoDte}${fechaInicio}_${fechaFin}.zip`;
              
              const url = window.URL.createObjectURL(data);
              const a = document.createElement('a');
              a.href = url;
              a.download = filename;
              document.body.appendChild(a);
              a.click();
              
              setTimeout(() => {
                  document.body.removeChild(a);
                  window.URL.revokeObjectURL(url);
              }, 100);
              
              this.downloading = false;
              this.alertService.success('Exito', 'Archivo descargado correctamente');
          },
          (error: any) => {
              if (error.error instanceof Blob) {
                  error.error.text().then((errorMessage: string) => {
                      this.alertService.error(errorMessage || 'Error al descargar');
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

        const url = `${this.apiService.baseUrl}/api/libro-iva/contribuyentes?${filtros}&token=${token}`;
        window.open(url, '_blank');
      }


}
