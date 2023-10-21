import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-asistencias',
  templateUrl: './asistencias.component.html'
})
export class AsistenciasComponent implements OnInit {

    public asistencias: any = [];
    public empleados: any = [];
    public loading = false;
    public buscador:any = '';
    public estado:any = '';

    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

  	constructor( 
  	    private apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
  	) { }

  	ngOnInit() {
        this.loadAll();
  	}

    public search(buscador:any){
        if(buscador && buscador.length > 2) {
            this.loading = true;
            this.apiService.read('empleados/asistencias/buscar/', buscador).subscribe(empleados => { 
                this.empleados = empleados;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('empleados/asistencias').subscribe(asistencias => {
            this.asistencias = asistencias;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }


    
    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('empleado/asistencia/', id) .subscribe(data => {
                for (let i = 0; i < this.asistencias.length; i++) { 
                    if (this.asistencias[i].id == data.id )
                        this.asistencias.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }


    openReportes(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    // Filtros
    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.usuario_id = '';
        }
        if(!this.empleados.length){
            this.apiService.getAll('empleados/list').subscribe(empleados => { 
                this.empleados = empleados;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }


    onFiltrar(){
        this.loading = true;
        this.apiService.store('empleados/asistencias/filtrar', this.filtro).subscribe(asistencias => { 
            this.asistencias = asistencias;
            this.modalRef.hide();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public asistenciaDiaria(){
        var ventana = window.open(this.apiService.baseUrl + "/api/empleados/asistencia-diaria/" + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    public asistenciaMensual(){
        var ventana = window.open(this.apiService.baseUrl + "/api/empleados/asistencia-mensual/" + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

}
