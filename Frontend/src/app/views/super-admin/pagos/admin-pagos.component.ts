import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { formatDate } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { CommonModule } from '@angular/common';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

@Component({
    selector: 'app-admin-pagos',
    templateUrl: './admin-pagos.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, PaginationComponent, PopoverModule, TooltipModule],
    
})
export class AdminPagosComponent extends BaseCrudComponent<any> implements OnInit {

    public pagos:any = {};
    public empresas:any = [];
    public planes:any = [];
    public pago:any = {};
    public maxDate: string = '';
    public selectedFile: File | null = null;

    constructor( 
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private route: ActivatedRoute, 
        private router: Router
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'pago/new',
            itemsProperty: 'pagos',
            itemProperty: 'pago',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            messages: {
                created: 'El pago fue añadido exitosamente.',
                updated: 'El pago fue guardado exitosamente.',
                createTitle: 'Pago guardado',
                updateTitle: 'Pago actualizado'
            },
            afterSave: () => {
                this.resetForm();
            }
        });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    ngOnInit() {
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha_transaccion';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 15;
        
        // Establecer fecha máxima como hoy
        this.maxDate = formatDate(new Date(), 'yyyy-MM-dd', 'en');
        
        this.loadAll();
    }

    public override loadAll(){
        this.loading = true;
        this.apiService.getAll('pagos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(pagos => {
                this.pagos = pagos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
    }

    public loadEmpresas() {
        this.apiService.getAll('empresas/obtenerEmpresas')
            .pipe(this.untilDestroyed())
            .subscribe(
                response => {
                    this.empresas = response;
                },
                error => {
                    this.alertService.error('Error al cargar empresas: ' + error);
                }
            );
    }

    public loadPlanes() {
        this.apiService.getAll('planes/obtenerPlanes')
            .pipe(this.untilDestroyed())
            .subscribe(
                response => {
                    this.planes = response;
                },
                error => {
                    this.alertService.error('Error al cargar planes: ' + error);
                }
            );
    }

    override openModal(template: TemplateRef<any>, pago?: any) {
        this.loadEmpresas();
        this.loadPlanes();

        this.resetForm();
        if (pago && pago.id) {
            this.pago = {...pago};
            // Convertir fechas a formato adecuado para input type=date
            if (this.pago.fecha_transaccion) {
                this.pago.fecha_transaccion = formatDate(this.pago.fecha_transaccion, 'yyyy-MM-dd', 'en');
            }
            if (this.pago.fecha_proximo_pago) {
                this.pago.fecha_proximo_pago = formatDate(this.pago.fecha_proximo_pago, 'yyyy-MM-dd', 'en');
            }
        }
        // No pasar `pago` como segundo argumento: openLargeModal hace spread del config y mezclaría el modelo con opciones de ngx-bootstrap.
        super.openLargeModal(template);
    }

    override closeModal(){
        super.closeModal();
        this.resetForm();
    }

    resetForm() {
        this.pago = {
            estado: 'pendiente',
            fecha_transaccion: formatDate(new Date(), 'yyyy-MM-dd', 'en'),
            fecha_proximo_pago: formatDate(new Date(Date.now() + 30*24*60*60*1000), 'yyyy-MM-dd', 'en')
        };
        this.selectedFile = null;
    }

    public onPlanSelected(planId: string) {
        if (!planId) return;
        
        const plan = this.planes.find((p: any) => p.id == planId);
        if (plan) {
            this.pago.monto = plan.precio;
            
            // Calcular fecha del próximo pago basado en duración del plan
            if (plan.duracion_dias) {
                const fechaBase = new Date();
                const fechaProximoPago = new Date(fechaBase.getTime() + plan.duracion_dias * 24 * 60 * 60 * 1000);
                this.pago.fecha_proximo_pago = formatDate(fechaProximoPago, 'yyyy-MM-dd', 'en');
            }
        }
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar este pago?')) {
            return;
        }

        this.loading = true;
        this.apiService.delete('pago/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.loadAll();
                    this.alertService.success('Exito','Pago eliminado exitosamente');
                    this.loading = false;
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

    public generarVenta(pago:any) {
        this.saving = true;
        this.apiService.read('pago/generar-venta/', pago.id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (venta) => {
                    this.alertService.success('Venta generada', 'La venta fue generada exitosamente.');
                    this.pago.venta = venta;
                    this.saving = false;
                    this.closeModal();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.saving = false;
                }
            });
    }

    openModalEdit(template: TemplateRef<any>, pago: any = {}) {
        this.openModal(template, pago);
    }

}
