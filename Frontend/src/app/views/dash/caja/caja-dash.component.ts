import { Component, OnInit, Input, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-caja-dash',
    templateUrl: './caja-dash.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})

export class CajaDashComponent extends BaseModalComponent implements OnInit {

    public caja:any = {};
    public usuario:any = {};
    public tipoAccion:any = null;
    public supervisor:any = {};
    public override loading:boolean = false;

    @ViewChild('mcorte')
    public corteTemplate!: TemplateRef<any>;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    public supervisorModalRef!: any; // BsModalRef

    constructor( 
        public apiService: ApiService,
        private router: Router,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        // this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.getAll('caja')
          .pipe(this.untilDestroyed())
          .subscribe(caja => {
            this.caja = caja;
            if (this.caja.corte == null || this.caja.corte.estado == 'Cerrada'){
                this.openModal(this.corteTemplate, {});
            }
            else{
                this.caja = caja;
                sessionStorage.setItem('worder_corte', JSON.stringify(caja.corte));
            }
            this.loading = false;
            this.cdr.markForCheck();

        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

    public modalSupervisor(tipo:any){
        this.tipoAccion = tipo;
        this.supervisor = {};
        this.supervisorModalRef = this.modalManager.openModal(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.store('usuario-validar', this.supervisor)
          .pipe(this.untilDestroyed())
          .subscribe(supervisor => {
            this.modalManager.closeModal(this.supervisorModalRef);
            this.supervisorModalRef = undefined;
            if(this.tipoAccion == 'X') {
                this.corteX();
            }
            else if (this.tipoAccion == 'Z'){
                this.cerrarCaja(supervisor);
            }
            else if (this.tipoAccion == 'Caja'){
                this.openModal(this.corteTemplate, {class: 'modal-xs', backdrop: 'static', keyboard: false});
                this.supervisor = {};
            }

            this.loading = false;
            this.cdr.markForCheck();
        },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public override openModal(template: TemplateRef<any>, corte:any) {
        this.caja.corte = corte;
        if(!corte.fecha) {
            this.caja.corte.fecha = this.apiService.date();
        }
        this.tipoAccion = 'Caja';
        this.supervisorModalRef = this.modalManager.openModal(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public storeCaja() {
        this.loading = true;

        if(this.caja.corte.saldo_inicial == null) {
            this.caja.corte.saldo_inicial = 0;
        }

        if(!this.caja.corte.id) {
            this.caja.corte.apertura = this.apiService.datetime();
            this.caja.corte.caja_id = this.caja.id;
            this.caja.corte.usuario_id = this.apiService.auth_user().id;
        }

        this.apiService.store('corte', this.caja.corte)
          .pipe(this.untilDestroyed())
          .subscribe(corte => {
            this.caja.corte = corte;
            sessionStorage.setItem('wagro_corte', JSON.stringify(corte));
            this.loading = false;
            if (this.modalRef) {
                this.closeModal();
            }
            this.loadAll();
            this.cdr.markForCheck();
        },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

    }

    public cerrarCaja(supervisor:any) {
        this.loading = true;
        // if (confirm('Confirma que desea cerrar el turno en caja')) { 
            this.caja.corte.supervisor_id = supervisor.id;
            this.caja.corte.cierre = this.apiService.datetime();
            this.apiService.store('corte', this.caja.corte)
              .pipe(this.untilDestroyed())
              .subscribe(corte => {
                this.router.navigate(['/login']);
                this.corteZ();
                this.alertService.success('Caja cerrada', 'La caja fue cerrada exitosamente.');
                this.loading = false;
                if (this.modalRef){
                    this.closeModal();
                }
                this.cdr.markForCheck();
            },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
        // }

    }

    public corteX(){
        window.open(this.apiService.baseUrl + '/api/corte/reporte/' + this.caja.corte.id + '?token=' + this.apiService.auth_token(), 'Corte #' + this.caja.corte.id, "top=50,left=300,width=400,height=600");
    }

    public corteZ(){
        window.open(this.apiService.baseUrl + '/api/caja/reporte-dia/' + this.caja.id + '?token=' + this.apiService.auth_token(), 'Corte #' + this.caja.corte.id, "top=50,left=300,width=400,height=600");
    }

}
