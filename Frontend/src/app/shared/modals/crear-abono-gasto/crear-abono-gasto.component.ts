import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';

@Component({
  selector: 'app-crear-abono-gasto',
  templateUrl: './crear-abono-gasto.component.html',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, NotificacionesContainerComponent],
})
export class CrearAbonoGastoComponent extends BaseComponent implements OnInit {

	@Input() gasto: any = {};
	@Output() update = new EventEmitter();
	public formaPagos: any = [];
    public bancos: any = [];
    public abono: any = {};
 	public loading = false;
    public saving = false;

	modalRef!: BsModalRef;

   constructor(
        protected apiService: ApiService, 
        protected alertService: AlertService,  
    	private route: ActivatedRoute, 
        private router: Router,
    	private modalService: BsModalService
    ) {
        super();
    }

	ngOnInit() {
        this.abono.total = this.gasto.saldo;
        this.abono.fecha = this.apiService.date();
        this.abono.id_gasto = this.gasto.id;
        this.abono.nombre_de = this.gasto.nombre_proveedor;
        this.abono.estado = 'Confirmado';
        this.abono.forma_pago = 'Efectivo';
        this.abono.detalle_banco = '';
        this.abono.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.abono.id_empresa = this.apiService.auth_user().id_empresa;
        this.abono.id_usuario = this.apiService.auth_user().id;

        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => { 
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('bancos/list')
            .pipe(this.untilDestroyed())
            .subscribe(bancos => {
                this.bancos = bancos;
            }, error => {this.alertService.error(error);});
	}

    public setTotal(total:any){
        this.abono.total = total;
        document.getElementById('total')!.focus();
    }
	
	public onSubmit() {
        this.saving = true;

        if(this.abono.total >= this.gasto.total){
            this.abono.concepto = 'Pago total';
        }else{
            this.abono.concepto = 'Abono';
        }

        this.apiService.store('gasto/abono', this.abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {
                this.update.emit();
                this.router.navigate(['/gastos']);
                this.saving = false;
            }, error => {this.alertService.error(error); this.saving = false; });

	}

}

