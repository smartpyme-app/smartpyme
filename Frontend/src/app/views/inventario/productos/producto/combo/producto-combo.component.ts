import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe } from '@pipes/sum.pipe';
import { ComboDetallesComponent } from './detalles/combo-detalles.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';

@Component({
    selector: 'app-producto-combo',
    templateUrl: './producto-combo.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ComboDetallesComponent],
    providers: [SumPipe],
    
})
export class ProductoComboComponent extends BaseComponent implements OnInit {

  public producto: any = {};
  public categorias: any = [];
  public subcategorias: any = [];
  public subcategRes: any = [];
  public proveedores: any = [];
  public usuario: any = {};
  public categoria: any = {};
  public bodegas: any = [];
  public loading = false;
  public guardar = false;
  public variants: Array<{ nombre: string, cantidad: number }> = [];

  constructor(
    protected apiService: ApiService, 
    protected alertService: AlertService,
    private route: ActivatedRoute, 
    private router: Router, 
    private sumPipe: SumPipe,
  ) {
    super();
    // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    this.addVariant();
  }

  ngOnInit() {
    this.producto.nombre = "";
    this.producto.detalles = [];
    this.producto.id_empresa = this.apiService.auth_user().id_empresa;
    this.producto.id_categoria = 3154;
    this.producto.tipo = 'Compuesto';
    this.usuario = this.apiService.auth_user();
    // subcategorias

    this.apiService.getAll('categorias/list')
      .pipe(this.untilDestroyed())
      .subscribe(categorias => {
      this.categorias = categorias;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('subcategorias')
      .pipe(this.untilDestroyed())
      .subscribe(subcategorias => {
      this.subcategorias = subcategorias;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('proveedores/list')
      .pipe(this.untilDestroyed())
      .subscribe(proveedores => {
      this.proveedores = proveedores;
      this.loading = false;
    }, error => { this.alertService.error(error); this.loading = false; });

    this.apiService.getAll('bodegas/list')
      .pipe(this.untilDestroyed())
      .subscribe(bodegas => {
      this.bodegas = bodegas;
    }, error => { this.alertService.error(error); });

  }

  public setCategoria(categoria: any) {
    if (categoria.subcategoria) {
      this.subcategRes.push(categoria);
      this.producto.id_subcategoria = categoria.id;

    } else {
      this.categorias.push(categoria);
      this.producto.id_categoria = categoria.id;
    }

  }

  public setCompuesto() {
    if (this.producto.tipo == 'Producto') {
      this.producto.tipo = 'Compuesto';
    } else {
      this.producto.tipo = 'Producto';
    }
  }

  // CALCULO DEL STOCK MULTIPLICADO
  public calCantidadenCombo() {
    if (this.producto.stock > 0) {
      this.producto.detalles.forEach((detalle: any) => {
        detalle.cantidad_combo = detalle.cantidad * this.producto.stock;
      });
    }
  }

  public calPrecioFinal() {
    if (this.usuario.empresa.iva > 0) {
      this.producto.impuesto = this.usuario.empresa.iva / 100;
      this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
    }
  }

  public calPrecioBase() {
    if (this.usuario.empresa.iva > 0 && this.producto.precio_final) {
      this.producto.impuesto = this.usuario.empresa.iva / 100;
      this.producto.precio = (parseFloat(this.producto.precio_final) / (1 + this.producto.impuesto)).toFixed(2);
    }
  }

  public onSubmit() {
    this.guardar = true;  
    this.producto.codigo = "CMPKIT" + this.producto.codigo;  
    this.apiService.store('producto/compuesto', this.producto)
      .pipe(this.untilDestroyed())
      .subscribe(producto => {
      this.guardar = false;
      if (!this.producto.id) {
        this.producto = producto;
      }
      this.alertService.success('success', 'Producto compuesto creado correctamente');
      this.router.navigate(['/productos']);
    }, error => { this.alertService.error(error); this.guardar = false; });
  }

  public barcode() {
    var ventana = window.open(this.apiService.baseUrl + "/api/barcode/" + this.producto.codigo + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
  }

  public verificarSiExiste() {
    if (this.producto.nombre) {
      this.apiService.getAll('productos', { nombre: this.producto.nombre, estado: 1, })
        .pipe(this.untilDestroyed())
        .subscribe(productos => {
        if (productos.data[0]) {
          this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
            'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' + this.apiService.appUrl + '/producto/editar/' + productos.data[0].id + '">Ver producto</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
          );
        }
        this.loading = false;
      }, error => { this.alertService.error(error); this.loading = false; });
    }
  }

  public sumTotal() {
    this.producto.costo = (parseFloat(this.sumPipe.transform(this.producto.detalles, 'total'))).toFixed(2);
  }

  public updatecompra(producto: any) {
    this.producto = producto;
    this.sumTotal();
  }

  // creacion de sku
  private correlativo: number = 1; // Inicialmente, este sería el primer SKU

  public updateInputValue(): void {
    const categoriaSeleccionada = this.categorias.find((c: any) => c.id == this.producto.id_categoria);

    const subcategoriaSeleccionada = this.subcategorias.find((s: any) => s.id == this.producto.id_subcategoria);

    this.subcategRes = this.subcategorias.filter((cat: any) => { return cat.id_cate_padre == event; });    

    this.producto.codigo =  `${categoriaSeleccionada?.nombre ? categoriaSeleccionada?.nombre.slice(0, 3).toUpperCase() : ''}${subcategoriaSeleccionada?.nombre ? subcategoriaSeleccionada?.nombre.slice(0, 3).toUpperCase() : ''}${this.correlativo.toString().padStart(5, '0')}`;
  
  }

  //   variantes

  addVariant(): void {
    this.variants.push({ nombre: '', cantidad: 0 });
  }

  removeVariant(index: number): void {
    this.variants.splice(index, 1);
  }


}
