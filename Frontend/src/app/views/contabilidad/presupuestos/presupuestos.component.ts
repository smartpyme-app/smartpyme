import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-presupuestos',
  templateUrl: './presupuestos.component.html'
})

export class PresupuestosComponent implements OnInit {

    public presupuestos:any = [];
    public presupuesto:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public proyectos:any = [];
    public usuario:any = {};
    public sucursales:any = [];
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarPresupuestos();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.id_proyecto = '';
        this.filtros.orden = 'fecha_inicio';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.filtrarPresupuestos();
    }

    public filtrarPresupuestos(){
        this.loading = true;
        this.apiService.getAll('presupuestos', this.filtros).subscribe(presupuestos => { 
            this.presupuestos = presupuestos;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
    }

    public setAnulacion(presupuesto:any, estado:any){
        presupuesto.enable = estado;
        if(confirm('Confirma realización la acción?')){
            this.apiService.store('presupuesto', presupuesto).subscribe(presupuesto => { 
                this.alertService.success('Presupuesto actualizado', 'El presupuesto fue actualizado exitosamente.');
            }, error => {this.alertService.error(error); });
        }
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.presupuestos.path + '?page='+ event.page, this.filtros).subscribe(presupuestos => { 
            this.presupuestos = presupuestos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public openFilter(template: TemplateRef<any>) {
        if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
            this.apiService.getAll('proyectos/list').subscribe(proyectos => { 
                this.proyectos = proyectos;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

}
