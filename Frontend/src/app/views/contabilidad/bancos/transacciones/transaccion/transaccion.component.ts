import { Component, OnInit,TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';

import * as moment from 'moment';

@Component({
    selector: 'app-transaccion',
    templateUrl: './transaccion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class TransaccionComponent extends BaseComponent implements OnInit {

    public transaccion:any = {};
    public cuentas:any = [];
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

	constructor( 
	    protected apiService: ApiService, 
	    protected alertService: AlertService,
	    private route: ActivatedRoute, 
	    private router: Router, 
	    private modalService: BsModalService,
	    private cdr: ChangeDetectorRef
	) {
        super();
    }

	ngOnInit() {
        this.loadAll();

        this.apiService.getAll('banco/cuentas/list')
          .pipe(this.untilDestroyed())
          .subscribe(cuentas => {
            this.cuentas = cuentas;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('banco/transaccion/', id)
              .pipe(this.untilDestroyed())
              .subscribe(transaccion => {
                this.transaccion = transaccion;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
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

        this.apiService.store('banco/transaccion', this.transaccion)
          .pipe(this.untilDestroyed())
          .subscribe(transaccion => {
            if (!this.transaccion.id) {
                this.alertService.success('Transacción guardado', 'El transaccion fue guardado exitosamente.');
            }else{
                this.alertService.success('Transacción creado', 'El transaccion fue añadido exitosamente.');
            }
            this.router.navigate(['/bancos/transacciones']);
            this.saving = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck();});
    }

    public setFile(event:any) {
        this.transaccion.file = event.target.files[0];
        
        let formData:FormData = new FormData();
        for (var key in this.transaccion) {
            formData.append(key, this.transaccion[key]);
        }

        this.loading = true;
        this.apiService.store('banco/transaccion', formData)
          .pipe(this.untilDestroyed())
          .subscribe(transaccion => {
            this.transaccion.url_referencia = transaccion.url_referencia;
            this.loading = false;
            this.alertService.success('Documento guardado', 'La transaccion fue actualizada exitosamente.');
            this.cdr.markForCheck();
        
            //Generar partida contable
            if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                this.apiService.store('contabilidad/partida/transaccion', transaccion)
                  .pipe(this.untilDestroyed())
                  .subscribe(transaccion => {
                    this.cdr.markForCheck();
                },error => {this.alertService.error(error); this.cdr.markForCheck();});
            }

        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

    public verDocumento(transaccion:any){
        var ventana = window.open(this.apiService.baseUrl + "/img/" + transaccion.url_referencia + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

}
