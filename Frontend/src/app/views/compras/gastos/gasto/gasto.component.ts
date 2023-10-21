import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-gasto',
  templateUrl: './gasto.component.html'
})
export class GastoComponent implements OnInit {

    public gasto:any = {};
    public categorias:any = [];
    public proveedores:any = [];
    public loading = false;
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
            this.apiService.read('gasto/', id).subscribe(gasto => {
                this.gasto = gasto;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.gasto = {};
            this.gasto.metodo_pago = 'Efectivo';
            this.gasto.condicion = 'Contado';
            this.gasto.estado = 'Pagado';
            this.gasto.fecha_pago = this.apiService.date();
            this.gasto.fecha = this.apiService.date();
            this.gasto.empresa_id = this.apiService.auth_user().empresa_id;
            this.gasto.sucursal_id = this.apiService.auth_user().sucursal_id;
            this.gasto.usuario_id = this.apiService.auth_user().id;
        }

        this.apiService.getAll('gastos/categorias').subscribe(categorias => {
            this.categorias = categorias;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setCategoria(categoria:any){
        this.categorias.push(categoria);
        this.gasto.categoria_id = categoria.id;
    }

    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.gasto.proveedor_id = proveedor.id;
    }

    public setFechaPago(){
        if (this.gasto.condicion == 'Contado') {
            this.gasto.estado = 'Pagado';    
            this.gasto.fecha_pago = moment().format('YYYY-MM-DD');
        }else{
            this.gasto.estado = 'Pendiente';
            this.gasto.fecha_pago = moment().add(this.gasto.condicion.split(' ')[0], 'days').format('YYYY-MM-DD');
        }
    }


    public onSubmit(){
        this.loading = true;
        this.apiService.store('gasto', this.gasto).subscribe(gasto => { 
            this.router.navigate(['/gastos']);
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
