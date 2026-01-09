import { Component, OnInit, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-kardex',
    templateUrl: './kardex.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class KardexComponent implements OnInit {

	public producto:any = [];
	public productos:any[] = [];
	public bodegas:any[] = [];
	public filtros:any = {};
	public loading:boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        private apiService: ApiService, 
        private alertService: AlertService,  
        private route: ActivatedRoute, 
        private router: Router,
        private cdr: ChangeDetectorRef
    ){ }

	ngOnInit() {
        this.filtros.inicio = this.apiService.date();
        this.filtros.fin = this.apiService.date();
        this.filtros.id_inventario = this.apiService.auth_user().id_sucursal;
        this.filtros.detalle = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        const id = +this.route.snapshot.paramMap.get('id')!;
        if(!isNaN(id)){
            this.filtros.id_producto = id;
            this.loadAll();
        }

        this.apiService.getAll('bodegas/list')
            .pipe(this.untilDestroyed())
            .subscribe(bodegas => {
                this.bodegas = bodegas;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public loadAll() {

     	this.loading = true; 
        this.apiService.getAll('productos/kardex', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(producto => {
                this.producto = producto;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

    }

    selectProducto(producto:any){
        this.filtros.id_producto = producto.id;
        this.cdr.markForCheck();
        // console.log(this.filtros);
    }

    public descargarKardex(){
        this.apiService.export('productos/kardex/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = this.apiService.slug(this.producto.nombre) + '-kardex.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.cdr.markForCheck();
          }, (error) => {console.error('Error al exportar kardex:', error); this.cdr.markForCheck(); }
        );
    }

}
