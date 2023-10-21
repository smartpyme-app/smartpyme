import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';


@Component({
  selector: 'app-devolucion-venta',
  templateUrl: './devolucion-venta.component.html'
})

export class DevolucionVentaComponent implements OnInit {

	public venta: any= {};
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

        this.loadAll();	        
	}

    public loadAll(){
	    const id = +this.route.snapshot.paramMap.get('id')!;
        this.apiService.read('devolucion/venta/', id).subscribe(venta => {
           this.venta = venta;
           this.detalles = venta.detalles;
           this.proveedor = venta.proveedor;
        });
    }

}
