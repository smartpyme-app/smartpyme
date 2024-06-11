import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-cheque',
  templateUrl: './cheque.component.html'
})
export class ChequeComponent implements OnInit {

    public cheque:any = {};
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.loadAll();

    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('banco/cheque/', id).subscribe(cheque => {
                this.cheque = cheque;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.cheque = {};
            this.cheque.estado = 'Pendiente';
            this.cheque.fecha = this.apiService.date();
            this.cheque.id_cuenta = 1;
            this.cheque.id_empresa = this.apiService.auth_user().id_empresa;
            this.cheque.id_usuario = this.apiService.auth_user().id;
        }

    }


    public onSubmit(){
        this.saving = true;

        this.apiService.store('banco/cheque', this.cheque).subscribe(cheque => {
            if (!this.cheque.id) {
                this.alertService.success('Cheque guardado', 'El cheque fue guardado exitosamente.');
            }else{
                this.alertService.success('Cheque creado', 'El cheque fue añadido exitosamente.');
            }
            this.router.navigate(['/bancos/cheques']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
