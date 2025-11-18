import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseFilteredPaginatedModalComponent } from '@shared/base/base-filtered-paginated-modal.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-inventario-salidas',
    templateUrl: './inventario-salidas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, PopoverModule, TooltipModule],
    
})
export class InventarioSalidasComponent extends BaseFilteredPaginatedModalComponent implements OnInit {

    public salidas:any = [];
    public salida:any = {};

    public usuarios:any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private router: Router, 
        private route: ActivatedRoute
    ){
        super(apiService, alertService, modalManager);
    }

    protected aplicarFiltros(): void {
        this.filtrar();
    }

    ngOnInit() {
        this.route.queryParams.pipe(this.untilDestroyed()).subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                usuario_id: params['usuario_id'] || '',
                estado: params['estado'] || '',
                tipo: params['tipo'] || '',
                inicio: params['inicio'] || '',
                fin: params['fin'] || '',
                orden: params['orden'] || 'fecha',
                direccion: params['direccion'] || 'desc',
                per_page: params['per_page'] || 10,
                page: params['page'] || 1,
            };

            this.filtrar();
        });
    }

    public loadAll() {
        this.filtros.buscador = '';
        this.filtros.usuario_id = '';
        this.filtros.estado = '';
        this.filtros.tipo = '';
        this.filtros.inicio = '';
        this.filtros.fin = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.per_page = 10;

        this.filtrar();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrar();
    }

    public filtrar(){
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;
        this.apiService.store('salidas/filtrar', this.filtros).pipe(this.untilDestroyed()).subscribe(salidas => { 
            this.salidas = salidas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setEstado(salida:any, estado:any){
        if(estado == 'Aprobada'){
            Swal.fire({
              title: '¿Estás seguro?',
              text: '¡Confirma aprobar la salida!',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, aprobar',
              cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.apiService.store('salida/aprobar/' + salida.id, {}).pipe(this.untilDestroyed()).subscribe(data => {
                        this.alertService.success('Salida aprobada correctamente', 'El registro fue aprobado exitosamente.');
                        this.filtrar();

                        //Generar partida contable
                        if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                            this.apiService.store('salida/partida-contable/' + data.id, {}).pipe(this.untilDestroyed()).subscribe(salida => {
                            },error => {this.alertService.error(error);});
                        }

                    }, error => {this.alertService.error(error); });
                }
            });
        }
        if(estado == 'Anulada'){
            Swal.fire({
              title: '¿Estás seguro?',
              text: '¡Confirma anular la salida!',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, anular',
              cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.apiService.store('salida/anular/' + salida.id, {}).pipe(this.untilDestroyed()).subscribe(data => {
                        this.alertService.success('Salida anulada correctamente', 'El registro fue anulado exitosamente.');
                        this.filtrar();
                    }, error => {this.alertService.error(error); });
                }
            });
        }
    }

    public delete(id:number) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¡Esta acción no se puede deshacer!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.apiService.delete('salida/', id).pipe(this.untilDestroyed()).subscribe(data => {
                    this.alertService.success('Salida eliminada correctamente', 'El registro fue eliminado exitosamente.');
                    this.filtrar();
                }, error => {this.alertService.error(error); });
            }
        });
    }

    // setPagination() ahora se hereda de BaseFilteredPaginatedComponent

    // Filtros
    openFilter(template: TemplateRef<any>) {
        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/filtrar/tipo/Empleado').pipe(this.untilDestroyed()).subscribe(usuarios => { 
                this.usuarios = usuarios.data;
            }, error => {this.alertService.error(error); });
        }
        this.openModal(template);
    }

    reemprimir(salida:any){
        window.open(this.apiService.baseUrl + '/api/reporte/salida/' + salida.id + '?token=' + this.apiService.auth_token());
    }

    descargar(){
        this.loading = true;
        window.open(this.apiService.baseUrl + '/api/salidas/exportar?token=' + this.apiService.auth_token());
        this.loading = false;
    }

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('salida', this.salida).pipe(this.untilDestroyed()).subscribe(salida => {
            this.alertService.success('Salida actualizada correctamente', 'El registro fue actualizado exitosamente.');
            this.saving = false;
            this.filtrar();
        }, error => {
            this.alertService.error(error);
            this.saving = false;
        });
    }

    generarPartidaContable(salida: any) {
        Swal.fire({
            title: '¿Generar partida contable?',
            text: '¿Estás seguro de que deseas generar la partida contable para esta salida?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.loading = true;
                this.apiService.store('salida/partida-contable/' + salida.id, {}).pipe(this.untilDestroyed()).subscribe(
                    (response) => {
                        this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
                        this.loading = false;
                        this.filtrar();
                    },
                    (error) => {
                        this.alertService.error(error);
                        this.loading = false;
                    }
                );
            }
        });
    }

    /**
     * Verifica si el usuario puede ver las opciones de inventario
     * Oculta ciertas opciones para Supervisores de la empresa 324
     */
    public puedeVerOpcionesInventario(): boolean {
        const user = this.apiService.auth_user();
        return !(user?.tipo === 'Supervisor' && user?.id_empresa === 324);
    }

    /**
     * Verifica si el usuario puede crear salidas
     */
    public puedeCrearSalida(): boolean {
        return this.apiService.canCreate() && this.puedeVerOpcionesInventario();
    }

}