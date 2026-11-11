import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import Swal from 'sweetalert2';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';

@Component({
    selector: 'app-devoluciones-compras',
    templateUrl: './devoluciones-compras.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class DevolucionesComprasComponent extends BaseCrudComponent<any> implements OnInit {

    public compras:any = {};
    public compra:any = {};
    public id_compra:any = null;
    public downloading:boolean = false;
    public proveedores:any = [];
    public usuarios:any = [];
    public comprasList:any = [];
    public sucursales:any = [];

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef,
        private facturacionElectronica: FacturacionElectronicaService,
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'devolucion/compra',
            itemsProperty: 'compras',
            itemProperty: 'compra',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La devolución de compra fue actualizada exitosamente.',
                updated: 'La devolución de compra fue actualizada exitosamente.',
                createTitle: 'Compra actualizada',
                updateTitle: 'Compra actualizada'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarCompras();
    }

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => {
                this.proveedores = proveedores;
            }, error => {this.alertService.error(error); });
    }

    public override loadAll() {
        this.loading = true;
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;
        this.filtrarCompras(false);
    }

    /** @param resetPage true al filtrar/ordenar/cambiar paginate; false al paginar o tras loadAll. */
    public filtrarCompras(resetPage = true): void {
        if (resetPage) {
            this.filtros.page = 1;
        }
        this.loading = true;
        this.apiService.getAll('devoluciones/compras', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(compras => {
                this.compras = compras;
                this.loading = false;
                this.closeModal();
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public setEstado(compra:any, enable:string){
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, anularlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            this.compra = compra;
            this.compra.enable = enable;
            this.onSubmit();
          }
        });
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;

        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        this.apiService.delete('devolucion/compra/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (deletedItem: any) => {
                    const index = this.compras.data?.findIndex((c: any) => c.id === deletedItem.id);
                    if (index !== -1 && index >= 0) {
                        this.compras.data.splice(index, 1);
                    }
                    this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error: (error: any) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCompras();
    }

    public override setPagination(event:any):void{
        this.filtros.page = event.page;
        this.filtrarCompras(false);
    }

    // Filtros

    openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => {
                this.proveedores = proveedores;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
                this.sucursales = sucursales;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        this.openModal(template);
    }

    override openModal(template: TemplateRef<any>) {
        this.id_compra = null;
        this.loading = true;
        this.apiService.getAll('compras/sin-devolucion')
            .pipe(this.untilDestroyed())
            .subscribe(compras => {
                this.comprasList = compras;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
        super.openModal(template);
    }

    // Evento de retorno (18) sobre compras de sujeto excluido

    esEventoRetorno(compra: any): boolean {
        return compra?.tipo_documento === 'Evento de retorno' && !!this.apiService.auth_user()?.empresa?.facturacion_electronica;
    }

    tieneDteInvalidacion(compra: any): boolean {
        return !!compra?.dte_invalidacion;
    }

    private reemplazarEnListado(compra: any): void {
        const idx = this.compras.data?.findIndex((c: any) => c.id === compra.id);
        if (idx !== undefined && idx !== -1 && this.compras.data) {
            this.compras.data[idx] = { ...compra };
        }
        this.cdr.markForCheck();
    }

    emitirEventoRetorno(compra: any): void {
        this.saving = true;
        this.facturacionElectronica.emitirDTEEventoRetornoCompra(compra).then((guardada: any) => {
            this.reemplazarEnListado({ ...compra, ...guardada });
            this.alertService.success('Evento de retorno', 'Emitido y sellado por Hacienda.');
        }).catch((error: any) => {
            this.alertService.warning('Evento de retorno no emitido', String(error?.error?.error ?? error?.message ?? error));
        }).finally(() => {
            this.saving = false;
            this.cdr.markForCheck();
        });
    }

    verDteEventoRetorno(compra: any, formato: 'pdf' | 'json', anulado = false): void {
        const ruta = formato === 'pdf' ? 'dte' : 'dte-json';
        window.open(`${this.apiService.baseUrl}/api/reporte/${ruta}/${compra.id}/18/?token=${this.apiService.auth_token()}&origen=compra${anulado ? '&documento=anulado' : ''}`, 'hola', 'width=400');
    }

    enviarCorreoEventoRetorno(compra: any): void {
        this.apiService.store('enviarDTE', { id: compra.id, tipo_dte: '18', origen: 'compra' })
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => this.alertService.success('Correo enviado', 'El evento de retorno fue enviado al proveedor.'),
                error: (error: any) => this.alertService.error(error),
            });
    }

    /** Invalida el evento en Hacienda y, si obtiene sello, anula la devolución (restaura inventario). */
    anularEventoRetorno(compra: any): void {
        if (!confirm('¿Confirma invalidar el evento de retorno en Hacienda?')) {
            return;
        }
        this.saving = true;
        const fallo = (error: any) => {
            this.alertService.error(error?.error?.descripcionMsg ?? error?.error?.observaciones ?? error);
            this.saving = false;
            this.cdr.markForCheck();
        };
        this.apiService.store('generarDTEAnuladoEventoRetornoCompra', { id: compra.id })
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (dte: any) => {
                    compra.dte_invalidacion = dte;
                    this.facturacionElectronica.firmarDTE(dte).pipe(this.untilDestroyed()).subscribe({
                        next: (dteFirmado: any) => {
                            if (dteFirmado.status == 'ERROR') {
                                fallo(dteFirmado.body?.mensaje);
                                return;
                            }
                            compra.dte_invalidacion.firmaElectronica = dteFirmado.body;
                            this.facturacionElectronica.anularDTE(compra, dteFirmado.body).pipe(this.untilDestroyed()).subscribe({
                                next: (res: any) => {
                                    if (res.estado == 'PROCESADO' && res.selloRecibido) {
                                        compra.dte_invalidacion.sello = res.selloRecibido;
                                        compra.enable = '0';
                                        this.apiService.store('devolucion/compra', compra).pipe(this.untilDestroyed()).subscribe({
                                            next: (guardada: any) => {
                                                this.reemplazarEnListado({ ...compra, ...guardada });
                                                this.alertService.success('Evento de retorno', 'Invalidado en Hacienda.');
                                                this.saving = false;
                                            },
                                            error: fallo,
                                        });
                                    }
                                },
                                error: fallo,
                            });
                        },
                        error: fallo,
                    });
                },
                error: fallo,
            });
    }

    public imprimir(compra:any){
        window.open(this.apiService.baseUrl + '/api/devolucion/facturacion/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('devoluciones/compras/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'devoluciones-compras.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
          }, (error) => { this.alertService.error(error); this.downloading = false; this.cdr.markForCheck(); }
        );
    }

}
