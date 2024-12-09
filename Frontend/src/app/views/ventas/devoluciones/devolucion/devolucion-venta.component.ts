import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-devolucion-venta',
  templateUrl: './devolucion-venta.component.html'
})

export class DevolucionVentaComponent implements OnInit {

	public devolucion: any= {};
	public detalles: any= [];
	public detalle: any = {};
	
	public proveedor: any = {};

    public loading = false;
    modalRef!: BsModalRef;
    
	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	    private modalService: BsModalService
	) { }

	ngOnInit() {
        this.devolucion.sub_total = 0;
        this.devolucion.iva = 0;
        this.devolucion.descuento = 0;
        this.devolucion.total = 0;
        this.loadAll();	        
	}

    public loadAll(){
	    const id = +this.route.snapshot.paramMap.get('id')!;
        this.apiService.read('devolucion/venta/', id).subscribe(devolucion => {
           this.devolucion = devolucion;
           this.detalles = devolucion.detalles;
           this.proveedor = devolucion.proveedor;
        });
    }

}
