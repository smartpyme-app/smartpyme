import { Component, OnInit, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-empleados',
  templateUrl: './empleados.component.html'
})

export class EmpleadosComponent implements OnInit {

	public empleados:any = [];
    public paginacion = [];
    public loading:boolean = false;
    public buscador:any = '';
    public sucursales:any = [];
    public cargos:any = [];

    public filtro:any = {};
    public filtrado:boolean = false;
    
    modalRef?: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

	ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
    	this.loading = true;
        this.apiService.getAll('empleados').subscribe(empleados => { 
            this.empleados = empleados;
    		this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public search(buscador:any){
        if(buscador && buscador.length > 2) {
            this.loading = true;
            this.apiService.read('empleados/buscar/', buscador).subscribe(empleados => { 
                this.empleados = empleados;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public setEstado(empleado:any){
        this.apiService.store('empleado', empleado).subscribe(empleado => { 
            this.alertService.success('Actualizado');
        }, error => {this.alertService.error(error); });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('empleado/', id) .subscribe(data => {
                for (let i = 0; i < this.empleados['data'].length; i++) { 
                    if (this.empleados['data'][i].id == data.id )
                        this.empleados['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.empleados.path + '?page='+ event.page).subscribe(empleados => { 
            this.empleados = empleados;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros
    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.tipo = '';
            this.filtro.sucursal_id = '';
        }
        if(!this.sucursales.data){
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
            this.apiService.getAll('cargos').subscribe(cargos => { 
                this.cargos = cargos;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('empleados/filtrar', this.filtro).subscribe(empleados => { 
            this.empleados = empleados;
            this.loading = false; this.filtrado = true;
            this.modalRef!.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


    public carnet(empleado:any){
        var ventana = window.open(this.apiService.baseUrl + "/api/empleado/carnet/" + empleado.id + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }


}

