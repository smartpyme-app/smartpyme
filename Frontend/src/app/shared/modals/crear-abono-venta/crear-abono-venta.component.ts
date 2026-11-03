import { Component, OnInit, OnChanges, SimpleChanges, Input, Output, EventEmitter } from '@angular/core';
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
export class CrearAbonoVentaComponent extends BaseComponent implements OnInit, OnChanges {

	@Input() venta: any = {};
	@Output() update = new EventEmitter();
	public formaPagos: any = [];
	public bancos: any = [];
    public usarCuentasEnSelect = false;
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
        this.inicializarAbono();
        this.cargarFormasDePago();
        this.cargarBancos();
	}

    ngOnChanges(changes: SimpleChanges): void {
        if (changes['venta'] && !changes['venta'].firstChange) {
            this.inicializarAbono();
        }
    }

    private inicializarAbono(): void {
        const saldo = (this.venta?.total ?? 0) - (this.venta?.abonos_sum_total ?? 0) - (this.venta?.devoluciones_sum_total ?? 0);
        this.abono.total = this.venta?.saldo ?? saldo;
        this.abono.fecha = this.apiService.date();
        this.abono.id_venta = this.venta.id;
        this.abono.nombre_de = this.venta.nombre_cliente;
        this.abono.estado = 'Confirmado';
        this.abono.forma_pago = 'Efectivo';
        this.abono.detalle_banco = '';
        this.abono.referencia = '';
        this.abono.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.abono.id_empresa = this.apiService.auth_user().id_empresa;
        this.abono.id_usuario = this.apiService.auth_user().id;
    }

    private cargarFormasDePago(): void {
        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => {
            this.formaPagos = formaPagos;
            this.cambioMetodoDePago();
        }, error => {this.alertService.error(error); });
    }

    private cargarBancos(): void {
        this.apiService.loadBancosParaAbono()
            .pipe(this.untilDestroyed())
            .subscribe(({ bancos, usarCuentas }) => {
                this.bancos = bancos;
                this.usarCuentasEnSelect = usarCuentas;
            }, error => { this.alertService.error(error); });
    }

    public requiereBanco(): boolean {
        const fp = this.abono?.forma_pago;
        return !!fp && fp !== 'Efectivo' && fp !== 'Wompi';
    }

    public cambioMetodoDePago() {
        if (!this.requiereBanco()) {
            this.abono.detalle_banco = '';
            return;
        }
        if (this.usarCuentasEnSelect || this.apiService.usarCuentasBancariasEnPagos()) {
            const formaPagoSeleccionada = this.formaPagos.find((fp: any) => fp.nombre === this.abono.forma_pago);
            if (formaPagoSeleccionada?.banco?.nombre_banco) {
                this.abono.detalle_banco = formaPagoSeleccionada.banco.nombre_banco;
            } else if (!this.bancos.some((b: any) => b.nombre_banco === this.abono.detalle_banco || b.nombre === this.abono.detalle_banco)) {
                this.abono.detalle_banco = '';
            }
        } else {
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

            if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                this.apiService.store('contabilidad/partida/cxc', abono)
                    .pipe(this.untilDestroyed())
                    .subscribe(abono => {
                },error => {this.alertService.error(error);});
            }
        }, error => {this.alertService.error(error); this.saving = false; });

	}

}
