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

    public setFile(event:any) {
        this.transaccion.file = event.target.files[0];
        
        let formData:FormData = new FormData();
        for (var key in this.transaccion) {
            formData.append(key, this.transaccion[key]);
        }

        this.loading = true;
        this.apiService.store('banco/transaccion', formData).subscribe(transaccion => {
            this.transaccion.url_referencia = transaccion.url_referencia;
            this.loading = false;
            this.alertService.success('Documento guardado', 'La transaccion fue actualizada exitosamente.');
        
            //Generar partida contable
            if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                this.apiService.store('contabilidad/partida/transaccion', transaccion).subscribe(transaccion => {
                },error => {this.alertService.error(error);});
            }

        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public verDocumento(transaccion:any){
        var ventana = window.open(this.apiService.baseUrl + "/img/" + transaccion.url_referencia + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

}
