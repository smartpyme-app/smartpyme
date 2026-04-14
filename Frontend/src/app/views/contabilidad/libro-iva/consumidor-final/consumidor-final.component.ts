import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-consumidor-final',
  templateUrl: './consumidor-final.component.html',
})

export class ConsumidorFinalComponent implements OnInit {

	public ivas:any[] = [];
    public years:any[] = [];
    public sucursales:any[] = [];
    public loading:boolean = false;
    public downloading:boolean = false;
    public filtros:any = {};
    modalRef!: BsModalRef;
    public tipoDescarga: string = '';

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

    public esDescargaZipDeclaracion(tipo: string): boolean {
        return tipo === 'dtes_zip' || tipo === 'dtes_pdf_zip';
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
            case 'libro_pdf':
                this.descargarLibroPDF();
                break;
            case 'anexo_csv':
                this.descargarAnexo();
                break;
            case 'dtes_zip':
                this.descargarDTEConsumidorFinal();
                break;
            case 'dtes_pdf_zip':
                this.descargarDTEsPdfZip();
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
          }, (error) => { this.manejarErrorDescarga(error); }
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
            this.manejarErrorDescarga(error);
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
            this.alertService.success('Éxito', 'Archivo descargado correctamente');
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

    public descargarDTEsPdfZip(): void {
        this.downloading = true;
        this.filtros.typeDTE = '01';
        this.apiService.export('libro-iva/consumidores/descargar-dttes-pdf', this.filtros, 900000).subscribe(
            (data: Blob) => {
                if (data.type === 'text/plain') {
                    data.text().then((errorMessage: string) => {
                        this.alertService.error(errorMessage);
                    });
                    this.downloading = false;
                    return;
                }
                if (data.size === 0) {
                    this.alertService.error('El archivo descargado está vacío');
                    this.downloading = false;
                    return;
                }
                const fechaInicio = this.filtros.inicio.replace(/-/g, '');
                const fechaFin = this.filtros.fin.replace(/-/g, '');
                const prefijo = this.filtros.estado_json === 'anulados' ? 'DTEs_PDF_anulados_' : 'DTEs_PDF_';
                const filename = `${prefijo}${fechaInicio}_${fechaFin}.zip`;
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
                this.alertService.success('Éxito', 'Archivo ZIP con PDFs descargado correctamente');
            },
            (error: any) => {
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
