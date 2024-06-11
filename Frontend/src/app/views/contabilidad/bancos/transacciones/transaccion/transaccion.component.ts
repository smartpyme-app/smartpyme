import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-transaccion',
  templateUrl: './transaccion.component.html'
})
export class TransaccionComponent implements OnInit {

    public transaccion:any = {};
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
            this.apiService.read('banco/transaccion/', id).subscribe(transaccion => {
                this.transaccion = transaccion;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.transaccion = {};
            this.transaccion.estado = 'Pendiente';
            this.transaccion.tipo = '';
            this.transaccion.id_cuenta = '';
            this.transaccion.fecha = this.apiService.date();
            this.transaccion.id_empresa = this.apiService.auth_user().id_empresa;
            this.transaccion.id_usuario = this.apiService.auth_user().id;
        }

    }

    public onSubmit(){
        this.saving = true;

        this.apiService.store('banco/transaccion', this.transaccion).subscribe(transaccion => {
            if (!this.transaccion.id) {
                this.alertService.success('Transacción guardado', 'El transaccion fue guardado exitosamente.');
            }else{
                this.alertService.success('Transacción creado', 'El transaccion fue añadido exitosamente.');
            }
            this.router.navigate(['/bancos/transacciones']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
