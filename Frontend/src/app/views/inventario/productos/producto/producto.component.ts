import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { ProductoInformacionComponent } from './informacion/producto-informacion.component';
import { ProductoComposicionComponent } from './composicion/producto-composicion.component';
import { ProductoPresentacionesComponent } from './presentaciones/producto-presentaciones.component';
import { ProductoInventariosComponent } from './inventario/producto-inventarios.component';
import { ProductoLotesComponent } from './lotes/producto-lotes.component';
import { ProductoPreciosComponent } from './precios/producto-precios.component';
import { ProductoProveedoresComponent } from './proveedores/producto-proveedores.component';
import { ProductoImagenesComponent } from './imagenes/producto-imagenes.component';

@Component({
  selector: 'app-producto',
  templateUrl: './producto.component.html',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    ProductoInformacionComponent,
    ProductoComposicionComponent,
    ProductoPresentacionesComponent,
    ProductoInventariosComponent,
    ProductoLotesComponent,
    ProductoPreciosComponent,
    ProductoProveedoresComponent,
    ProductoImagenesComponent,
  ],
})
export class ProductoComponent implements OnInit {

	public producto: any = {};
	public categorias:any[] = [];
  	public loading = false;
	public mostrarModuloPresentaciones = false;
	public lotesRefreshKey = 0;

	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
      private funcionalidadesService: FuncionalidadesService,
	) {	}

	ngOnInit() {
      this.funcionalidadesService.verificarAcceso('modulo-presentaciones-productos').subscribe({
        next: (acceso) => {
          this.mostrarModuloPresentaciones = acceso && this.apiService.isModuloPresentaciones();
        },
        error: () => {
          this.mostrarModuloPresentaciones = false;
        }
      });

		this.route.params.subscribe((params:any) => {
	      	if (params.id) {
		        this.loading = true;
		        this.apiService.read('producto/', params.id).subscribe(producto => {
		            this.producto = producto;
                const pct = (producto.porcentaje_impuesto != null && producto.porcentaje_impuesto !== '') ? Number(producto.porcentaje_impuesto) : (this.apiService.auth_user()?.empresa?.iva ?? 0);
                this.producto.impuesto = Number(pct) / 100;
                this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);

	              this.loading = false;
		        },error => {this.alertService.error(error);this.loading = false;});
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

	      	}
	    });
	}

	loadAll(){

	}

	onProductoGuardado(producto: any) {
		if (producto?.id) {
			Object.assign(this.producto, producto);
		}
		if (this.producto.inventario_por_lotes || producto?.migracion_lotes) {
			this.lotesRefreshKey++;
		}
	}

}
