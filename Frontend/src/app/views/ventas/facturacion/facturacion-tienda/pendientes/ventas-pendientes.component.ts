import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-ventas-pendientes',
  templateUrl: './ventas-pendientes.component.html'
})
export class VentasPendientesComponent implements OnInit {

	modalRef!: BsModalRef;

    public ventas:any = [];
    public loading = false;


	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService,
	    private route: ActivatedRoute, private router: Router,
    ) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

	ngOnInit() {
    }
    
    openModal(template: TemplateRef<any>) {
        this.loading = true;
        this.apiService.getAll('ventas/pendientes').subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
        this.modalRef = this.modalService.show(template, {class: 'modal-lg'});
    }
    
    selectOrden(orden:any){
        this.modalRef.hide();
        this.router.navigate(['facturacion'], {queryParams: { venta_id: orden.id }});
    }

}
