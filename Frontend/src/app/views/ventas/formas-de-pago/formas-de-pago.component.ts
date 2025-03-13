import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-formas-de-pago',
  templateUrl: './formas-de-pago.component.html'
})
export class FormasDePagoComponent implements OnInit {

    public formas_pago: any = [];
    public forma_pago: any = {};
    public empresa: any = {};
    public bancos: any = [];
    public loading = false;
    public saving = false;
    public wompiActivo = false;

    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService,
        private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.empresa = this.apiService.auth_user().empresa;
        this.loadAll();

        if (this.apiService.isModuloBancos()) {
            this.apiService.getAll('banco/cuentas/list').subscribe(
                (bancos) => { this.bancos = bancos; },
                (error) => this.alertService.error(error)
            );
        }
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('formas-de-pago').subscribe(
            (formas_pago) => {
                this.formas_pago = formas_pago;
                if (this.apiService.isModuloBancos()) {
                    this.wompiActivo = formas_pago.some((item: any) => item.nombre === 'Wompi');
                } else {
                    const wompi = formas_pago.find((item: any) => item.nombre === 'Wompi');
                    this.wompiActivo = wompi ? wompi.activo : false;
                }
                this.loading = false;
            },
            (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
        );
    }

    public onSubmit(nombre?: any) {
        this.saving = true;

        if (this.apiService.isModuloBancos()) {
            this.forma_pago.id_empresa = this.apiService.auth_user().id_empresa;
            this.apiService.store('forma-de-pago', this.forma_pago).subscribe(
                () => {
                    this.alertService.success('Forma de pago guardada', 'La forma de pago fue guardada exitosamente.');
                    this.saving = false;
                    this.modalRef?.hide();
                    this.loadAll();
                },
                (error) => {
                    this.alertService.error(error);
                    this.saving = false;
                }
            );
        } else {
            this.forma_pago.nombre = nombre;
            this.forma_pago.id_empresa = this.apiService.auth_user().id_empresa;
            this.apiService.store('forma-de-pago', this.forma_pago).subscribe(
                () => {
                    this.alertService.success('Formas de pago actualizadas', 'Las formas de pago fueron actualizadas exitosamente.');
                    this.saving = false;
                    this.loadAll();
                },
                (error) => {
                    this.alertService.error(error);
                    this.saving = false;
                }
            );
        }
    }

    public openModal(template: TemplateRef<any>, forma_pago?: any) {
        this.forma_pago = forma_pago ? { ...forma_pago } : { nombre: '', id_banco: '' };
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public deleteFormaPago(forma_pago: any) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta forma de pago será eliminada.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminarlo',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.apiService.delete('forma-de-pago/', forma_pago.id).subscribe(
                    () => {
                        this.alertService.success('Forma de pago eliminada', 'La forma de pago fue eliminada exitosamente.');
                        this.loadAll();
                    },
                    (error) => this.alertService.error(error)
                );
            }
        });
    }

    public onSubmitWompi() {
        this.saving = true;
        this.apiService.store('wompi', this.empresa).subscribe(
            () => {
                this.saving = false;
                this.alertService.success('Conexión exitosa', 'Conexión con Wompi exitosa, ya puede crear enlaces de pago para tus ventas.');
            },
            (error) => {
                this.alertService.error(error);
                this.saving = false;
            }
        );
    }
}
