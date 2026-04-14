import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-inventario-entradas',
    templateUrl: './inventario-entradas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, PopoverModule, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class InventarioEntradasComponent extends BaseCrudComponent<any> implements OnInit {

    public entradas:any = [];
    public entrada:any = {};
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
            endpoint: 'entrada',
            itemsProperty: 'entradas',
            itemProperty: 'entrada',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El registro fue actualizado exitosamente.',
                updated: 'El registro fue actualizado exitosamente.',
                deleted: 'El registro fue eliminado exitosamente.',
                createTitle: 'Entrada actualizada correctamente',
                updateTitle: 'Entrada actualizada correctamente',
                deleteTitle: 'Entrada eliminada correctamente',
                deleteConfirm: '¿Estás seguro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrar();
    }

    ngOnInit() {
        this.route.queryParams
          .pipe(this.untilDestroyed())
          .subscribe({
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
        this.apiService.store('entradas/filtrar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (entradas) => {
                this.entradas = entradas;
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

    public setEstado(entrada:any, estado:any){
        if(estado == 'Aprobada'){
            Swal.fire({
              title: '¿Estás seguro?',
              text: '¡Confirma aprobar la entrada!',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, aprobar',
              cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.apiService.store('entrada/aprobar/' + entrada.id, {})
                      .pipe(this.untilDestroyed())
                      .subscribe({
                        next: (data) => {
                            this.alertService.success('Entrada aprobada correctamente', 'El registro fue aprobado exitosamente.');
                            this.filtrar();

                            //Generar partida contable
                            if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                                this.apiService.store('entrada/partida-contable/' + data.id, {})
                                  .pipe(this.untilDestroyed())
                                  .subscribe({
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
              text: '¡Confirma anular la entrada!',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, anular',
              cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.apiService.store('entrada/anular/' + entrada.id, {})
                      .pipe(this.untilDestroyed())
                      .subscribe({
                        next: () => {
                            this.alertService.success('Entrada anulada correctamente', 'El registro fue anulado exitosamente.');
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
                this.apiService.delete('entrada/', id)
                  .pipe(this.untilDestroyed())
                  .subscribe({
                    next: () => {
                        this.alertService.success('Entrada eliminada correctamente', 'El registro fue eliminado exitosamente.');
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
            this.apiService.getAll('usuarios/filtrar/tipo/Empleado')
              .pipe(this.untilDestroyed())
              .subscribe({
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

    reemprimir(entrada:any){
        window.open(this.apiService.baseUrl + '/api/reporte/entrada/' + entrada.id + '?token=' + this.apiService.auth_token());
    }

    descargar(){
        this.loading = true;
        window.open(this.apiService.baseUrl + '/api/entradas/exportar?token=' + this.apiService.auth_token());
        this.loading = false;
        this.cdr.markForCheck();
    }

    public override async onSubmit() {
        this.saving = true;            
        try {
            await this.apiService.store('entrada', this.entrada)
          .pipe(this.untilDestroyed())
                .toPromise();
            
                this.alertService.success('Entrada actualizada correctamente', 'El registro fue actualizado exitosamente.');
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

    generarPartidaContable(entrada: any) {
        Swal.fire({
            title: '¿Generar partida contable?',
            text: '¿Estás seguro de que deseas generar la partida contable para esta entrada?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.loading = true;
                this.apiService.store('entrada/partida-contable/' + entrada.id, {})
                  .pipe(this.untilDestroyed())
                  .subscribe({
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
     * Verifica si el usuario puede crear entradas
     */
    public puedeCrearEntrada(): boolean {
        return this.apiService.puedeCrearOperacionesInventarioEnUi();
    }

}
