import { Component, OnInit,TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { TagInputModule } from 'ngx-chips';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';

@Component({
    selector: 'app-proveedor-detalles',
    templateUrl: './proveedor-detalles.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TagInputModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ProveedorDetallesComponent extends BaseComponent implements OnInit {

    public proveedor:any = {};
    public loading = false;
    modalRef?: BsModalRef;

	constructor( 
	    protected apiService: ApiService, 
	    protected alertService: AlertService,
	    private route: ActivatedRoute, 
	    private router: Router, 
	    private modalService: BsModalService,
	    private cdr: ChangeDetectorRef
	) {
        super();
    }

	ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.route.params
            .pipe(this.untilDestroyed())
            .subscribe((params:any) => {
                if (params.id) {
                    this.loading = true;
                    this.apiService.read('proveedor/', params.id)
                        .pipe(this.untilDestroyed())
                        .subscribe(proveedor => {
                            this.proveedor = proveedor;
                            this.loading = false;
                            this.cdr.markForCheck();
                        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
                }else{
                    this.proveedor = {};
                    this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
                    this.proveedor.id_usuario = this.apiService.auth_user().id;
                    this.cdr.markForCheck();
                }
            });
    }

}
