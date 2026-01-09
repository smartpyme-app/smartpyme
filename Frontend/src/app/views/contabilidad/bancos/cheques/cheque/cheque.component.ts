import { Component, OnInit,TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';

import * as moment from 'moment';

@Component({
    selector: 'app-cheque',
    templateUrl: './cheque.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ChequeComponent extends BaseComponent implements OnInit {

    public cheque:any = {};
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
        // Cargar cuentas bancarias disponibles
        this.apiService.getAll('banco/cuentas/list')
          .pipe(this.untilDestroyed())
          .subscribe(cuentas => {
            this.cuentas = cuentas;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});

        this.loadAll();

    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('banco/cheque/', id)
              .pipe(this.untilDestroyed())
              .subscribe(cheque => {
                this.cheque = cheque;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
        }else{
            this.cheque = {};
            this.cheque.estado = 'Pendiente';
            this.cheque.fecha = this.apiService.date();
            this.cheque.id_cuenta = '';  // Dejar vacío para que el usuario seleccione
            this.cheque.id_empresa = this.apiService.auth_user().id_empresa;
            this.cheque.id_usuario = this.apiService.auth_user().id;
        }

    }


    public onSubmit(){
        // Validar que se haya seleccionado una cuenta
        if (!this.cheque.id_cuenta) {
            this.alertService.error('Debe seleccionar una cuenta bancaria');
            return;
        }

        this.saving = true;

        this.apiService.store('banco/cheque', this.cheque)
          .pipe(this.untilDestroyed())
          .subscribe(cheque => {
            if (!this.cheque.id) {
                this.alertService.success('Cheque guardado', 'El cheque fue guardado exitosamente.');
            }else{
                this.alertService.success('Cheque creado', 'El cheque fue añadido exitosamente.');
            }
            this.router.navigate(['/bancos/cheques']);
            this.saving = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck();});
    }

}
