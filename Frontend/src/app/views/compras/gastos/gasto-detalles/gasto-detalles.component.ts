import { Component, OnInit,TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import { CrearAbonoGastoComponent } from '@shared/modals/crear-abono-gasto/crear-abono-gasto.component';

@Component({
    selector: 'app-gasto-detalles',
    templateUrl: './gasto-detalles.component.html',
    standalone: true,
    imports: [CommonModule, PipesModule, RouterModule, FormsModule, CrearAbonoGastoComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GastoDetallesComponent extends BaseComponent implements OnInit {

    public gasto:any = {};
    public loading = false;
    modalRef?: BsModalRef;

	constructor( 
	    protected apiService: ApiService, 
	    protected alertService: AlertService,
	    private route: ActivatedRoute, 
	    private router: Router, 
	    private modalService: BsModalService,
	    private location: Location,
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
                    this.apiService.read('gasto/', params.id)
                        .pipe(this.untilDestroyed())
                        .subscribe(gasto => {
                            this.gasto = gasto;
                            this.loading = false;
                            this.cdr.markForCheck();
                        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
                }else{
                    this.gasto = {};
                    this.gasto.id_empresa = this.apiService.auth_user().id_empresa;
                    this.gasto.id_usuario = this.apiService.auth_user().id;
                    this.cdr.markForCheck();
                }
            });
    }

    public openAbono(template: TemplateRef<any>, gasto:any){
        this.gasto = gasto;
        this.modalRef = this.modalService.show(template);
    }

    public setEstado(abono: any){
        this.apiService.store('gasto/abono', abono)
            .pipe(this.untilDestroyed())
            .subscribe(abono => {
                this.loadAll();
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    public goBack() {
        this.location.back();
    }

}
