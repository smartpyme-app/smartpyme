import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-admin-venta',
  templateUrl: './admin-venta.component.html'
})
export class AdminVentaComponent implements OnInit {

    public venta:any = {};
    public proyecto:any ={};
    public usuario:any = {};
    public loading = false;
    public saving = false;

    modalRef!: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();
    }

    public loadAll(){
        if(this.modalRef){
            this.modalRef.hide();
        }
        
        this.venta.id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;

        this.apiService.read('transaccion/', this.venta.id).subscribe(venta => {
        this.venta = venta;

        if(this.venta.id_proyecto){
            this.apiService.read('proyecto/',this.venta.id_proyecto).subscribe(proyecto => {
                this.proyecto = proyecto;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});

        }

        this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public setEstado(abono:any){
        this.saving = false;
        this.apiService.store('venta/abono', abono).subscribe(abono => {
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public imprimirRecibo(abono:any){
        window.open(this.apiService.baseUrl + '/api/venta/abono/imprimir/' + abono.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.modalRef = this.modalService.show(template);
    }

}
