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
    selector: 'app-crear-abono-venta',
    templateUrl: './crear-abono-venta.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NotificacionesContainerComponent],

})
export class CrearAbonoVentaComponent extends BaseComponent implements OnInit {

	@Input() venta: any = {};
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
        this.abono.total = this.venta.saldo;
        this.abono.fecha = this.apiService.date();
        this.abono.id_venta = this.venta.id;
        this.abono.nombre_de = this.venta.nombre_cliente;
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
            if (this.apiService.isModuloBancos() && this.abono.forma_pago && this.abono.forma_pago !== 'Efectivo' && this.abono.forma_pago !== 'Wompi') {
                const formaPagoSeleccionada = formaPagos.find((fp: any) => fp.nombre === this.abono.forma_pago);
                if (formaPagoSeleccionada?.banco?.nombre_banco && !this.abono.detalle_banco) {
                    this.abono.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
                }
            }
        }, error => {this.alertService.error(error); });

        if (this.apiService.isModuloBancos()) {
            this.apiService.getAll('banco/cuentas/list').subscribe(bancos => {
                this.bancos = bancos;
            }, error => {this.alertService.error(error);});
        } else {
            this.apiService.getAll('bancos/list').subscribe(bancos => {
                this.bancos = bancos;
            }, error => {this.alertService.error(error);});
        }
	}

    public cambioMetodoDePago() {
        if (this.apiService.isModuloBancos() && this.abono.forma_pago && this.abono.forma_pago !== 'Efectivo' && this.abono.forma_pago !== 'Wompi') {
            const formaPagoSeleccionada = this.formaPagos.find((fp: any) => fp.nombre === this.abono.forma_pago);
            if (formaPagoSeleccionada?.banco?.nombre_banco) {
                this.abono.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            } else {
                this.abono.detalle_banco = '';
            }
        } else if (this.abono.forma_pago === 'Efectivo' || this.abono.forma_pago === 'Wompi') {
            this.abono.detalle_banco = '';
        }
    }

    public setTotal(total:any){
        this.abono.total = total;
        document.getElementById('total')!.focus();
    }

	public onSubmit() {
        this.saving = true;

        if(this.abono.total >= this.venta.total){
            this.abono.concepto = 'Pago total';
        }else{
            this.abono.concepto = 'Abono';
        }

        this.apiService.store('venta/abono', this.abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {

            this.update.emit();
            this.router.navigate(['/ventas/abonos']);
            this.alertService.modal = false;
            this.saving = false;

            //Generar partida contable
            if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                this.apiService.store('contabilidad/partida/cxc', abono)
                    .pipe(this.untilDestroyed())
                    .subscribe(abono => {
                },error => {this.alertService.error(error);});
            }
        }, error => {this.alertService.error(error); this.saving = false; });

	}

}
