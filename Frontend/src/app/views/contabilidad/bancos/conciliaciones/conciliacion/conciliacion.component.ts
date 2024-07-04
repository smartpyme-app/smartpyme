import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-conciliacion',
  templateUrl: './conciliacion.component.html'
})
export class ConciliacionComponent implements OnInit {

    public conciliacion:any = {};
    public cuentas:any = [];
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.loadAll();

        this.apiService.getAll('banco/cuentas/list').subscribe(cuentas => {
            this.cuentas = cuentas;
        }, error => {this.alertService.error(error);});

    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('banco/conciliacion/', id).subscribe(conciliacion => {
                this.conciliacion = conciliacion;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.conciliacion = {};
            this.conciliacion.id_cuenta = '';
            this.conciliacion.fecha = this.apiService.date();
            this.conciliacion.desde = this.apiService.date();
            this.conciliacion.hasta = this.apiService.date();
            this.conciliacion.saldo_anterior = 0;
            this.conciliacion.salidas = 0;
            this.conciliacion.entradas = 0;
            this.conciliacion.saldo_final = 0;
            this.conciliacion.diferencia = 0;
            this.conciliacion.id_empresa = this.apiService.auth_user().id_empresa;
            this.conciliacion.id_usuario = this.apiService.auth_user().id;
        }

    }

    public onSubmit(){
        this.saving = true;

        this.apiService.store('banco/conciliacion', this.conciliacion).subscribe(conciliacion => {
            if (!this.conciliacion.id) {
                this.alertService.success('Transacción guardado', 'El conciliacion fue guardado exitosamente.');
            }else{
                this.alertService.success('Transacción creado', 'El conciliacion fue añadido exitosamente.');
            }
            this.router.navigate(['/bancos/conciliaciones']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
