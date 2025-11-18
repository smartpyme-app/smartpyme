import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';


@Component({
    selector: 'app-presupuestos',
    templateUrl: './presupuestos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PopoverModule, TooltipModule],
    
})

export class PresupuestosComponent extends BasePaginatedComponent implements OnInit {

    public presupuestos: PaginatedResponse<any> = {} as PaginatedResponse;
    public presupuesto:any = {};
    public buscador:any = '';

    public clientes:any = [];
    public usuarios:any = [];
    public proyectos:any = [];
    public usuario:any = {};
    public sucursales:any = [];
    public override filtros:any = {};

    modalRef!: BsModalRef;

    constructor(apiService: ApiService, alertService: AlertService,
                private modalService: BsModalService
    ){
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.presupuestos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.presupuestos = data;
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe(sucursales => { 
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
        this.apiService.getAll('presupuestos', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe(presupuestos => { 
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
            this.apiService.store('presupuesto', presupuesto)
              .pipe(this.untilDestroyed())
              .subscribe(presupuesto => { 
                this.alertService.success('Presupuesto actualizado', 'El presupuesto fue actualizado exitosamente.');
            }, error => {this.alertService.error(error); });
        }
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public openFilter(template: TemplateRef<any>) {
        if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
            this.apiService.getAll('proyectos/list')
              .pipe(this.untilDestroyed())
              .subscribe(proyectos => { 
                this.proyectos = proyectos;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

}
