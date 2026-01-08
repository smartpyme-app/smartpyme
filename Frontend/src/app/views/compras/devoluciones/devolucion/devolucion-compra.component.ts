import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';


@Component({
    selector: 'app-devolucion-compra',
    templateUrl: './devolucion-compra.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class DevolucionCompraComponent extends BaseComponent implements OnInit {

	public devolucion: any= {};
	public detalles: any= [];
	public detalle: any = {};
	
	public proveedor: any = {};

    public loading = false;
    modalRef!: BsModalRef;
    
	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	    private modalService: BsModalService,
	    private cdr: ChangeDetectorRef
	) {
        super();
    }

	ngOnInit() {
        this.loadAll();
	}

    public loadAll(){
        this.loading = true;
	    const id = +this.route.snapshot.paramMap.get('id')!;
        this.apiService.read('devolucion/compra/', id)
            .pipe(this.untilDestroyed())
            .subscribe(devolucion => {
                this.devolucion = devolucion;
                this.detalles = devolucion.detalles;
                this.proveedor = devolucion.proveedor;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

}
