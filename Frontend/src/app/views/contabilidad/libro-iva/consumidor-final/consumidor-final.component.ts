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

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {   
        const currentYear = new Date().getFullYear();
        const currentMonth = new Date().getMonth() + 1;
        
        for (let i = 0; i <= 10; i++) {
          this.years.push(currentYear - i);
        }

        // Recuperar filtros del localStorage si existen
        const savedFilters = localStorage.getItem('consumidores_filtros');
        if (savedFilters) {
            this.filtros = JSON.parse(savedFilters);
        } else {
            // Valores por defecto si no hay filtros guardados
            this.filtros.id_sucursal = '';
            this.filtros.tipo_documento = 'Crédito fiscal';
            this.filtros.anio = currentYear;
            this.filtros.mes = currentMonth;
            this.filtros.time = 'day';
        }

        this.setTime();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        // Guardar filtros en localStorage antes de cargar
        localStorage.setItem('consumidores_filtros', JSON.stringify(this.filtros));

        this.apiService.getAll('libro-iva/consumidores', this.filtros).subscribe(ivas => { 
            this.ivas = ivas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setTime() {
        this.filtros.inicio = moment([this.filtros.anio, this.filtros.mes - 1]).startOf('month').format('YYYY-MM-DD');
        this.filtros.fin = moment([this.filtros.anio, this.filtros.mes - 1]).endOf('month').format('YYYY-MM-DD');
        this.loadAll();
    }

    public openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    } 

    public limpiarFiltros() {
        const currentYear = new Date().getFullYear();
        const currentMonth = new Date().getMonth() + 1;

        this.filtros = {
            id_sucursal: '',
            tipo_documento: 'Crédito fiscal',
            anio: currentYear,
            mes: currentMonth,
            time: 'day'
        };

        localStorage.removeItem('consumidores_filtros');
        this.setTime();
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

    public descargarAnexo(){
        this.downloading = true;
        this.apiService.export('libro-iva/consumidores/descargar-anexo', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Anexo-consumidores.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

}
