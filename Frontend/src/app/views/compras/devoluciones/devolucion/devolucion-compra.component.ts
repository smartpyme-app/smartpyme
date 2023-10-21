import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';


@Component({
  selector: 'app-devolucion-compra',
  templateUrl: './devolucion-compra.component.html'
})

export class DevolucionCompraComponent implements OnInit {

	public compra: any= {};
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
        this.loading = true;
	    const id = +this.route.snapshot.paramMap.get('id')!;
        this.apiService.read('devolucion/compra/', id).subscribe(compra => {
           this.compra = compra;
           this.detalles = compra.detalles;
            this.loading = false;
           this.proveedor = compra.proveedor;
        });
    }

}
