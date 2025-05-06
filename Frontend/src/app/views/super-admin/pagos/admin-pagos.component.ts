import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';
import { formatDate } from '@angular/common';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-admin-pagos',
  templateUrl: './admin-pagos.component.html'
})
export class AdminPagosComponent implements OnInit {

    public pagos:any = [];
    public empresas:any = [];
    public planes:any = [];
    public pago:any = {};
    public loading = false;
    public saving = false;
    public filtros:any = {};
    public maxDate: string = '';

    modalRef!: BsModalRef;
    selectedFile: File | null = null;

    constructor(
        public apiService: ApiService,
        private alertService: AlertService,
        private route: ActivatedRoute,
        private router: Router,
        private modalService: BsModalService
    ) { }

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
        this.apiService.getAll('pagos', this.filtros).subscribe(pagos => {
            this.pagos = pagos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    public loadEmpresas() {
        this.apiService.getAll('empresas/obtenerEmpresas').subscribe(
            response => {
                this.empresas = response;
            },
            error => {
                this.alertService.error('Error al cargar empresas: ' + error);
            }
        );
    }

    public loadPlanes() {
        this.apiService.getAll('planes/obtenerPlanes').subscribe(
            response => {
                this.planes = response;
            },
            error => {
                this.alertService.error('Error al cargar planes: ' + error);
            }
        );
    }

    public openModal(template: TemplateRef<any>, pago: any = {}) {
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
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
    }

    closeModal(){
        this.modalRef.hide();
        this.alertService.modal = false;
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

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.pagos.path + '?page='+ event.page).subscribe(pagos => {
            this.pagos = pagos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
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

    public delete(id: number) {
        if (confirm('¿Desea eliminar este pago?')) {
            this.apiService.delete('pago/', id).subscribe(data => {
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

        this.apiService.store('pago/new', pagoData).subscribe(
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
          this.modalRef.hide();
          this.alertService.modal = false;
        },error => {this.alertService.error(error); this.saving = false; });
  }

  openModalEdit(template: TemplateRef<any>, pago: any = {}) {
    this.pago = {...pago};
    this.openModal(template, pago);
  }
}
