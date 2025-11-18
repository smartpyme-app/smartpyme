import { Component, OnInit,TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';

@Component({
    selector: 'app-cuenta',
    templateUrl: './cuenta.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class CuentaComponent implements OnInit {

    public cuenta:any = {};
    // public bancos:any = [];
    public catalogo:any = [];
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private modalService: BsModalService
	) { }

	ngOnInit() {
        this.loadAll();

        // this.apiService.getAll('bancos/list').subscribe(bancos => {
        //     this.bancos = bancos;
        // }, error => {this.alertService.error(error);});

        this.apiService.getAll('catalogo/list')
          .pipe(this.untilDestroyed())
          .subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('banco/cuenta/', id)
              .pipe(this.untilDestroyed())
              .subscribe(cuenta => {
                this.cuenta = cuenta;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.cuenta = {};
            this.cuenta.nombre_banco = '';
            this.cuenta.id_cuenta_contable = '';
            this.cuenta.id_empresa = this.apiService.auth_user().id_empresa;
        }

    }

    public onSubmit(){
        this.saving = true;

        this.apiService.store('banco/cuenta', this.cuenta)
          .pipe(this.untilDestroyed())
          .subscribe(cuenta => {
            if (!this.cuenta.id) {
                this.alertService.success('Cuenta guardada', 'La cuenta fue guardada exitosamente.');
            }else{
                this.alertService.success('Cuenta creada', 'La cuenta fue añadida exitosamente.');
            }
            this.router.navigate(['/bancos/cuentas']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
