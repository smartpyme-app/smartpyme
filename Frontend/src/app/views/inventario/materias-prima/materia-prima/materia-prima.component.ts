import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { MateriaPrimaInformacionComponent } from './informacion/materia-prima-informacion.component';
import { ProductoInventariosComponent } from '@views/inventario/productos/producto/inventario/producto-inventarios.component';
import { ProductoComposicionComponent } from '@views/inventario/productos/producto/composicion/producto-composicion.component';
import { ProductoAjustesComponent } from '@views/inventario/productos/producto/historial/ajustes/producto-ajustes.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-materia-prima',
    templateUrl: './materia-prima.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, MateriaPrimaInformacionComponent, ProductoInventariosComponent, ProductoComposicionComponent, ProductoAjustesComponent],
    
})
export class MateriaPrimaComponent implements OnInit {

	public producto: any = {};
	public categorias:any[] = [];
  public loading = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	) {
		this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
	}

	ngOnInit() {
	    
	    this.loadAll();

	}

	loadAll(){
		const id = +this.route.snapshot.paramMap.get('id')!;
		    
	    if(isNaN(id)){
	        this.producto = {};
	        this.producto.tipo = 'Materia Prima';
	        this.producto.empresa_id = this.apiService.auth_user().empresa_id;
	    }
	    else{
	        // Optenemos el producto
	        this.loading = true;
	        this.apiService.read('materia-prima/', id)
	          .pipe(this.untilDestroyed())
	          .subscribe(producto => {
	           this.producto = producto;
	           this.loading = false;
	        },error => {this.alertService.error(error);this.loading = false;});
	    }

	}

	public async onSubmit() {
	    this.loading = true;
	    try {
	        const isNew = !this.producto.id;
	        const productoGuardado = await this.apiService.store('materia-prima', this.producto)
	            .pipe(this.untilDestroyed())
	            .toPromise();
	        
	        if (isNew) {
	            this.producto = productoGuardado;
	            this.router.navigate(['/materia-prima/' + productoGuardado.id]);
	        }
	        
	        this.alertService.success('Materia prima guardada', 'La materia prima fue guardada exitosamente');
	    } catch (error: any) {
	        this.alertService.error(error);
	    } finally {
	        this.loading = false;
	    }
	}
	

}
