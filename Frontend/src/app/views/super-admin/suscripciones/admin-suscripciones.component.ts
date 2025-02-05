import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-admin-suscripciones',
  templateUrl: './admin-suscripciones.component.html'
})
export class AdminSuscripcionesComponent implements OnInit {
    public suscripciones: any = [];
    public suscripcion: any = {};
    public usuario: any = {};
    public filtros: any = {};
    public loading: boolean = false;
    public saving: boolean = false;

    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();
    }

    public loadAll() {
        this.filtros = {
            estado: '',
            buscador: '',
            orden: 'fecha_ultimo_pago',
            direccion: 'desc',
            paginate: 10
        };
        
        this.filtrarSuscripciones();
    }

    public filtrarSuscripciones() {
        this.loading = true;
        this.apiService.getAll('suscripciones', this.filtros).subscribe(
            suscripciones => { 
                this.suscripciones = suscripciones;
                this.loading = false;
                if(this.modalRef) {
                    this.modalRef.hide();
                }
            }, 
            error => {
                this.alertService.error(error); 
                this.loading = false;
            }
        );
    }

    public setPagination(event: any): void {
        this.loading = true;
        this.apiService.paginate(this.suscripciones.path + '?page='+ event.page, this.filtros)
            .subscribe(
                suscripciones => { 
                    this.suscripciones = suscripciones;
                    this.loading = false;
                }, 
                error => {
                    this.alertService.error(error); 
                    this.loading = false;
                }
            );
    }

    public openDetalles(template: TemplateRef<any>, suscripcion: any) {
        this.suscripcion = suscripcion;
        this.modalRef = this.modalService.show(template);
    }

    public cancelarSuscripcion(suscripcion: any) {
        if(confirm('¿Está seguro de cancelar esta suscripción?')) {
            this.saving = true;
            this.apiService.store('suscripcion/cancelar/' + suscripcion.id, {})
                .subscribe(
                    response => {
                        this.saving = false;
                        this.alertService.success('Éxito', 'Suscripción cancelada exitosamente');
                        this.filtrarSuscripciones();
                    },
                    error => {
                        this.alertService.error(error);
                        this.saving = false;
                    }
                );
        }
    }

    public activarSuscripcion(suscripcion: any) {
        if(confirm('¿Está seguro de activar esta suscripción?')) {
            this.saving = true;
            this.apiService.store('suscripcion/activar/' + suscripcion.id, {})
                .subscribe(
                    response => {
                        this.saving = false;
                        this.alertService.success('Éxito', 'Suscripción activada exitosamente');
                        this.filtrarSuscripciones();
                    },
                    error => {
                        this.alertService.error(error);
                        this.saving = false;
                    }
                );
        }
    }
}