import { Component, OnInit, TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { ProductoInformacionComponent } from './informacion/producto-informacion.component';
import { ProductoComposicionComponent } from './composicion/producto-composicion.component';
import { ProductoInventariosComponent } from './inventario/producto-inventarios.component';
import { ProductoPreciosComponent } from './precios/producto-precios.component';
import { ProductoProveedoresComponent } from './proveedores/producto-proveedores.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-producto',
    templateUrl: './producto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ProductoInformacionComponent, ProductoComposicionComponent, ProductoInventariosComponent, ProductoPreciosComponent, ProductoProveedoresComponent],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ProductoComponent implements OnInit {

	public producto: any = {};
	public categorias:any[] = [];
  public loading = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor(
	    public apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	    private cdr: ChangeDetectorRef
	) {	}

	ngOnInit() {

		this.route.params
		  .pipe(this.untilDestroyed())
		  .subscribe((params:any) => {
	      	if (params.id) {
		        this.loading = true;
		        this.apiService.read('producto/', params.id).subscribe(producto => {
		            this.producto = producto;
                const pct = (producto.porcentaje_impuesto != null && producto.porcentaje_impuesto !== '') ? Number(producto.porcentaje_impuesto) : (this.apiService.auth_user()?.empresa?.iva ?? 0);
                this.producto.impuesto = Number(pct) / 100;
                this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
                this.loading = false;
                this.cdr.markForCheck();
		    },error => {this.alertService.error(error);this.loading = false; this.cdr.markForCheck();});
	      	} else {
				this.producto = {};
				this.producto.tipo = 'Producto';
				this.producto.medida = 'Unidad';
				this.producto.id_empresa = this.apiService.auth_user().id_empresa;
				this.producto.porcentaje_impuesto = this.apiService.auth_user().empresa.iva ?? null;

				if (this.route.snapshot.queryParamMap.get('tipo')!) {
				    this.producto.tipo = this.route.snapshot.queryParamMap.get('tipo')!;
				    this.producto.precio = 0;
				}
				this.cdr.markForCheck();

	      	}
	    });
	}

	loadAll(){

	}



}
