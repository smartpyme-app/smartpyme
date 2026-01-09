import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
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
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-inventario-salidas',
    templateUrl: './inventario-salidas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, PopoverModule, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class InventarioSalidasComponent extends BaseCrudComponent<any> implements OnInit {

    public salidas:any = [];
    public salida:any = {};
    public usuarios:any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private router: Router, 
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'salida',
            itemsProperty: 'salidas',
            itemProperty: 'salida',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El registro fue actualizado exitosamente.',
                updated: 'El registro fue actualizado exitosamente.',
                deleted: 'El registro fue eliminado exitosamente.',
                createTitle: 'Salida actualizada correctamente',
                updateTitle: 'Salida actualizada correctamente',
                deleteTitle: 'Salida eliminada correctamente',
                deleteConfirm: '¿Estás seguro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrar();
    }

    ngOnInit() {
        this.route.queryParams.pipe(this.untilDestroyed()).subscribe({
            next: (params) => {
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
                this.cdr.markForCheck();
            }
        });
    }

    public override loadAll() {
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
        this.apiService.store('salidas/filtrar', this.filtros).pipe(this.untilDestroyed()).subscribe({
            next: (salidas) => {
                this.salidas = salidas;
                this.loading = false;
                this.cdr.markForCheck();
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            }
        });
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
                    this.apiService.store('salida/aprobar/' + salida.id, {}).pipe(this.untilDestroyed()).subscribe({
                        next: (data) => {
                            this.alertService.success('Salida aprobada correctamente', 'El registro fue aprobado exitosamente.');
                            this.filtrar();

                            //Generar partida contable
                            if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                                this.apiService.store('salida/partida-contable/' + data.id, {}).pipe(this.untilDestroyed()).subscribe({
                                    next: () => {
                                        this.cdr.markForCheck();
                                    },
                                    error: (error) => {
                                        this.alertService.error(error);
                                        this.cdr.markForCheck();
                                    }
                                });
                            }
                            this.cdr.markForCheck();
                        },
                        error: (error) => {
                            this.alertService.error(error);
                            this.cdr.markForCheck();
                        }
                    });
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
                    this.apiService.store('salida/anular/' + salida.id, {}).pipe(this.untilDestroyed()).subscribe({
                        next: () => {
                            this.alertService.success('Salida anulada correctamente', 'El registro fue anulado exitosamente.');
                            this.filtrar();
                            this.cdr.markForCheck();
                        },
                        error: (error) => {
                            this.alertService.error(error);
                            this.cdr.markForCheck();
                        }
                    });
                }
            });
        }
    }

    public override delete(id:number) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¡Esta acción no se puede deshacer!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.apiService.delete('salida/', id).pipe(this.untilDestroyed()).subscribe({
                    next: () => {
                        this.alertService.success('Salida eliminada correctamente', 'El registro fue eliminado exitosamente.');
                        this.filtrar();
                        this.cdr.markForCheck();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                        this.cdr.markForCheck();
                    }
                });
            }
        });
    }

    // Filtros
    openFilter(template: TemplateRef<any>) {
        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/filtrar/tipo/Empleado').pipe(this.untilDestroyed()).subscribe({
                next: (usuarios) => {
                    this.usuarios = usuarios.data;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.cdr.markForCheck();
                }
            });
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
        this.cdr.markForCheck();
    }

    public override async onSubmit() {
        this.saving = true;            
        try {
            await this.apiService.store('salida', this.salida)
                .pipe(this.untilDestroyed())
                .toPromise();
            
                this.alertService.success('Salida actualizada correctamente', 'El registro fue actualizado exitosamente.');
                this.filtrar();
                this.cdr.markForCheck();
        } catch (error: any) {
                this.alertService.error(error);
                this.cdr.markForCheck();
        } finally {
                this.saving = false;
                this.cdr.markForCheck();
            }
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
                this.apiService.store('salida/partida-contable/' + salida.id, {}).pipe(this.untilDestroyed()).subscribe({
                    next: () => {
                        this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
                        this.loading = false;
                        this.filtrar();
                        this.cdr.markForCheck();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                        this.loading = false;
                        this.cdr.markForCheck();
                    }
                });
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
