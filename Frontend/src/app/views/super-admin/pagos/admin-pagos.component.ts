import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { formatDate } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { CommonModule } from '@angular/common';

@Component({
    selector: 'app-admin-pagos',
    templateUrl: './admin-pagos.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, PaginationComponent],
    
})
export class AdminPagosComponent extends BasePaginatedModalComponent implements OnInit {

    public pagos: PaginatedResponse<any> = {} as PaginatedResponse;
    public empresas:any = [];
    public planes:any = [];
    public pago:any = {};
    public override filtros:any = {};
    public maxDate: string = '';

    selectedFile: File | null = null;

    constructor( 
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private route: ActivatedRoute, 
        private router: Router
    ) {
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.pagos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.pagos = data;
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

    public loadAll(){
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

    override openModal(template: TemplateRef<any>, pago: any = {}) {
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

    // setPagination() ahora se hereda de BasePaginatedComponent


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

    public delete(id: number) {
        if (confirm('¿Desea eliminar este pago?')) {
            this.apiService.delete('pago/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                    this.loadAll(); // Recargar la lista
                    this.alertService.success('Exito','Pago eliminado exitosamente');
                }, error => {
                    this.alertService.error(error);
                });
        }
    }

    public onSubmit() {
        this.saving = true;
        
        // Asegurarse de que las fechas estén en el formato correcto para el backend
        const pagoData = {...this.pago};
        
        this.apiService.store('pago/new', pagoData)
            .pipe(this.untilDestroyed())
            .subscribe(
                response => {
                if (!this.pago.id) {
                    this.alertService.success('Pago guardado', 'El pago fue añadido exitosamente.');
                } else {
                    this.alertService.success('Pago actualizado', 'El pago fue guardado exitosamente.');
                }
                this.resetForm();
                this.saving = false;
                this.closeModal();
                this.loadAll(); // Recargar la lista de pagos
            },
            error => {
                this.alertService.error('Error al guardar pago: ' + error);
                this.saving = false;
            }
        );
    }

    public generarVenta(pago:any) {
        this.saving = true;
        this.apiService.read('pago/generar-venta/', pago.id).subscribe(venta => {
          this.alertService.success('Venta generada', 'La venta fue generada exitosamente.');
            this.pago.venta = venta;
            this.saving = false;
          this.closeModal();
        },error => {this.alertService.error(error); this.saving = false; });
  }

  openModalEdit(template: TemplateRef<any>, pago: any = {}) {
    this.pago = {...pago};
    this.openModal(template, pago);
  }
}
