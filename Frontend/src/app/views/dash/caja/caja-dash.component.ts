import { Component, OnInit, Input, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-caja-dash',
  templateUrl: './caja-dash.component.html'
})

export class CajaDashComponent implements OnInit {

    public caja:any = {};
    public usuario:any = {};
    public tipoAccion:any = null;
    public supervisor:any = {};
    public loading:boolean = false;

    @ViewChild('mcorte')
    public corteTemplate!: TemplateRef<any>;
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;


    constructor( 
        public apiService: ApiService, private router: Router, private alertService: AlertService, private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('caja').subscribe(caja => {
            this.caja = caja;
            if (this.caja.corte == null || this.caja.corte.estado == 'Cerrada'){
                this.openModal(this.corteTemplate, {});
            }
            else{
                this.caja = caja;
                sessionStorage.setItem('worder_corte', JSON.stringify(caja.corte));
            }
            this.loading = false;

        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public modalSupervisor(tipo:any){
        this.tipoAccion = tipo;
        this.supervisor = {};
        this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.apiService.store('usuario-validar', this.supervisor).subscribe(supervisor => {
            this.modalRef.hide();
            if(this.tipoAccion == 'X') {
                this.corteX();
            }
            else if (this.tipoAccion == 'Z'){
                this.cerrarCaja(supervisor);
            }
            else if (this.tipoAccion == 'Caja'){
                this.modalRef = this.modalService.show(this.corteTemplate, {class: 'modal-xs', backdrop: 'static', keyboard: false});
                this.supervisor = {};
            }

            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, corte:any) {
        this.caja.corte = corte;
        if(!corte.fecha) {
            this.caja.corte.fecha = this.apiService.date();
        }
        this.tipoAccion = 'Caja';
        this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});

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

        this.apiService.store('corte', this.caja.corte).subscribe(corte => {
            this.caja.corte = corte;
            sessionStorage.setItem('wagro_corte', JSON.stringify(corte));
            this.loading = false;
            this.modalRef.hide();
            this.loadAll();
        },error => {this.alertService.error(error); this.loading = false; });

    }

    public cerrarCaja(supervisor:any) {
        this.loading = true;
        // if (confirm('Confirma que desea cerrar el turno en caja')) { 
            this.caja.corte.supervisor_id = supervisor.id;
            this.caja.corte.cierre = this.apiService.datetime();
            this.apiService.store('corte', this.caja.corte).subscribe(corte => {
                this.router.navigate(['/login']);
                this.corteZ();
                this.alertService.success('Caja cerrada', 'La caja fue cerrada exitosamente.');
                this.loading = false;
                if (this.modalRef){
                    this.modalRef.hide();
                }
            },error => {this.alertService.error(error); this.loading = false; });
        // }

    }

    public corteX(){
        window.open(this.apiService.baseUrl + '/api/corte/reporte/' + this.caja.corte.id + '?token=' + this.apiService.auth_token(), 'Corte #' + this.caja.corte.id, "top=50,left=300,width=400,height=600");
    }

    public corteZ(){
        window.open(this.apiService.baseUrl + '/api/caja/reporte-dia/' + this.caja.id + '?token=' + this.apiService.auth_token(), 'Corte #' + this.caja.corte.id, "top=50,left=300,width=400,height=600");
    }


}
