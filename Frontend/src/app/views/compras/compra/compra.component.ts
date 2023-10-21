import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';
import { SumPipe }     from '../../../pipes/sum.pipe';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-compra',
  templateUrl: './compra.component.html',
  providers: [ SumPipe ]
})

export class CompraComponent implements OnInit {

	public compra: any= {};
	public detalle: any = {};
    public proveedores: any = [];
	public detalleModificado: any = {};
    public loading = false;
    modalRef!: BsModalRef;
    
	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	    private modalService: BsModalService, private sumPipe:SumPipe
	) { }

	ngOnInit() {

	    
	    const id = +this.route.snapshot.paramMap.get('id')!;
	        
        if(isNaN(id)){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.cargarDatosIniciales();
            // Optenemos el compra
            this.apiService.read('compra/', id).subscribe(compra => {
               	this.compra = compra;
        		this.loading = false;
            });
        }

        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); });

	}

	cargarDatosIniciales(){
		this.compra = {};
		this.compra.proveedor = {};
        this.compra.proveedor.empresa_id = this.apiService.auth_user().empresa_id;;
		this.compra.detalles = [];
        this.compra.fecha_pago = this.apiService.date();
		this.compra.fecha = this.apiService.date();
		this.compra.tipo = 'Interna';
        this.compra.estado = 'Pagada';
        this.compra.condicion = 'Contado';
        this.compra.metodo_pago = 'Efectivo';
        this.compra.tipo_documento = 'Factura';
        this.compra.descuento = 0;
		this.detalle = {};
		this.sumTotal();
		this.compra.bodega_id = this.apiService.auth_user().bodega_id;
        this.compra.usuario_id = this.apiService.auth_user().id;
        this.compra.empresa_id = this.apiService.auth_user().empresa_id;
	}

    updateOrden(compra:any) {
        this.compra = compra;
        this.sumTotal();
    }

    public setFechaPago(){
        if (this.compra.condicion == 'Contado') {
            this.compra.estado = 'Pagada';    
            this.compra.fecha_pago = moment().format('YYYY-MM-DD');
        }else{
            this.compra.estado = 'Pendiente';
            this.compra.fecha_pago = moment().add(this.compra.condicion.split(' ')[0], 'days').format('YYYY-MM-DD');
        }
    }

    public sumTotal() {
        this.compra.subtotal = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'subtotal'))).toFixed(2);
        this.compra.iva_percibido = this.compra.percepcion ? this.compra.subtotal * 0.01 : 0; 
        this.compra.iva_retenido = this.compra.retencion ? this.compra.subtotal * 0.01 : 0;

        this.compra.iva = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'iva'))).toFixed(2);
        this.compra.descuento = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'descuento') + parseFloat(this.compra.descuento))).toFixed(2);
        this.compra.subcosto = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'subcosto'))).toFixed(2);
        this.compra.no_sujeta = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'no_sujeta'))).toFixed(2);
        this.compra.exenta = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'exenta'))).toFixed(2);
        this.compra.gravada = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'gravada'))).toFixed(2);
        this.compra.total = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total'))  + parseFloat(this.compra.iva_percibido) - parseFloat(this.compra.iva_retenido)).toFixed(2);
    }

	// Proveedor
    public setProveedor(proveedor:any){
        this.proveedores.push(proveedor);
        this.compra.proveedor_id = proveedor.id;
        if(this.compra.proveedor.tipo == "Grande") {
        	this.compra.retencion = 1;
        	this.sumTotal();
        }
    }

	public onSubmit() {

		this.loading = true;
	    this.apiService.store('compra/facturacion', this.compra).subscribe(compra => {
            if (!this.compra.id)
    	        this.router.navigate(['/compras']);
	        this.alertService.success("Guardado");
            
	    },error => {this.alertService.error(error); this.loading = false; });

	}


}
