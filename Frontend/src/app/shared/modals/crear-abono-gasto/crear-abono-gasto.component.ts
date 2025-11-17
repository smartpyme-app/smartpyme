import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-abono-gasto',
  templateUrl: './crear-abono-gasto.component.html'
})
export class CrearAbonoGastoComponent implements OnInit {

	@Input() gasto: any = {};
	@Output() update = new EventEmitter();
	public formaPagos: any = [];
    public bancos: any = [];
    public abono: any = {};
 	public loading = false;
    public saving = false;

	modalRef!: BsModalRef;

   constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

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

        this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('bancos/list').subscribe(bancos => {
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

        this.apiService.store('gasto/abono', this.abono).subscribe(abono => {
            this.update.emit();
            this.router.navigate(['/gastos']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false; });

	}

}

