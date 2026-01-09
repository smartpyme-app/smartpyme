import { Component, OnInit, TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

declare var $:any;

@Component({
    selector: 'app-detalle-ventas',
    templateUrl: './detalle-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})

export class DetalleVentasComponent implements OnInit {

	public ventas:any = [];
    public categorias:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    public filtro:any = {};
    modalRef!: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        public apiService: ApiService, private alertService: AlertService, 
        private modalService: BsModalService,
        private cdr: ChangeDetectorRef
    ){}

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('categorias')
            .pipe(this.untilDestroyed())
            .subscribe(categorias => { 
                this.categorias = categorias;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); });
    }

    public loadAll() {

        if(!this.filtro.inicio) {
            this.filtro.inicio     = this.apiService.date();
            this.filtro.fin        = this.apiService.date();
            this.filtro.nombre     = '';
            this.filtro.categoria_id  = '';
        }

        this.loading = true;

        this.apiService.store('ventas/detalle', this.filtro)
            .pipe(this.untilDestroyed())
            .subscribe(ventas => { 
                this.ventas = ventas;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

    }


}
