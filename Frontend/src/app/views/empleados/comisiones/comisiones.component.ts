import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import * as moment from 'moment';

@Component({
  selector: 'app-comisiones',
  templateUrl: './comisiones.component.html'
})
export class ComisionesComponent implements OnInit {

    public comisiones: any = [];
    public empleados: any = [];
    public loading = false;

    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

  	constructor( 
  	    private apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
  	) { }

  	ngOnInit() {

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.sucursal_id = '';
            this.filtro.usuario_id = '';
        }
        if(!this.empleados.data){
            this.apiService.getAll('empleados').subscribe(empleados => { 
                this.empleados = empleados.data;
            }, error => {this.alertService.error(error); });
        }
  	    
        this.loadAll();

  	}

    public loadAll(){
        this.loading = true;
        this.apiService.store('comisiones/filtrar', this.filtro).subscribe(comisiones => { 
            this.comisiones = comisiones;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setEstado(comision:any, estado:string){
        comision.estado = estado;
        if (estado == 'Pagada') {
            comision.fecha = this.apiService.date();
        }
        this.apiService.store('comision', comision).subscribe(comision => { 
            this.alertService.success('Comision ' + estado);
        }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('comision/', id) .subscribe(data => {
                for (let i = 0; i < this.comisiones['data'].length; i++) { 
                    if (this.comisiones['data'][i].id == data.id )
                        this.comisiones['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    // Filtros
    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.empleado_id = '';
            this.filtro.tipo = '';
            this.filtro.estado = '';
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('comisiones/filtrar', this.filtro).subscribe(comisiones => { 
            this.comisiones = comisiones;
            this.loading = false; this.filtrado = true;
            this.modalRef!.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
