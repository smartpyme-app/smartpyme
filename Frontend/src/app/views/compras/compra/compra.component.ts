import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { CrearAbonoCompraComponent } from '@shared/modals/crear-abono-compra/crear-abono-compra.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-compra',
    templateUrl: './compra.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, CrearAbonoCompraComponent],
    
})
export class CompraComponent implements OnInit {

    public compra:any = {};
    public loading = false;

    modalRef!: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {

        this.loadAll();

    }

    public loadAll(){
        if(this.modalRef){
            this.modalRef.hide();
        }
        this.compra.id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;
        this.apiService.read('compra/', this.compra.id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (compra) => {
                    this.compra = compra;
                    this.loading = false;
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

    public openAbono(template: TemplateRef<any>, compra:any){
        this.compra = compra;
        this.modalRef = this.modalService.show(template);
    }


}
