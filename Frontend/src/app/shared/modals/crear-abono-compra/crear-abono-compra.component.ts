import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-abono-compra',
  templateUrl: './crear-abono-compra.component.html'
})
export class CrearAbonoCompraComponent implements OnInit {

	@Input() compra: any = {};
	@Output() update = new EventEmitter();
	public formaPagos: any = [];
    // public bancos: any = [];
    public abono: any = {};
 	public loading = false;
    public saving = false;

	modalRef!: BsModalRef;

   constructor(public apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {
        this.abono.total = this.compra.saldo;
        this.abono.fecha = this.apiService.date();
        this.abono.id_compra = this.compra.id;
        this.abono.nombre_de = this.compra.nombre_proveedor;
        this.abono.estado = 'Confirmado';
        this.abono.forma_pago = 'Efectivo';
        this.abono.detalle_banco = '';
        this.abono.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.abono.id_empresa = this.apiService.auth_user().id_empresa;
        this.abono.id_usuario = this.apiService.auth_user().id;

        this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });

        // this.apiService.getAll('bancos/list').subscribe(bancos => {
        // this.apiService.getAll('banco/cuentas/list').subscribe(bancos => {
        //     this.bancos = bancos;
        // }, error => {this.alertService.error(error);});
	}

    public setTotal(total:any){
        this.abono.total = total;
        document.getElementById('total')!.focus();
    }
	
	public onSubmit() {
        this.saving = true;

        if(this.abono.total >= this.compra.total){
            this.abono.concepto = 'Pago total';
        }else{
            this.abono.concepto = 'Abono';
        }

        this.apiService.store('compra/abono', this.abono).subscribe(abono => {
            this.alertService.modal = false;
            this.update.emit();
            this.router.navigate(['/compras/abonos']);
            this.saving = false;

            //Generar partida contable
            if(this.apiService.auth_user().empresa.generar_partidas == 'Auto'){
                this.apiService.store('contabilidad/partida/cxp', abono).subscribe(abono => {
                },error => {this.alertService.error(error);});
            }

        }, error => {this.alertService.error(error); this.saving = false; });

	}

}
