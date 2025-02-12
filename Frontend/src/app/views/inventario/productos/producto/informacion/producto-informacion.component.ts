import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';
import { CrearCategoriaComponent } from '@shared/modals/crear-categoria/crear-categoria.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ChangeDetectionStrategy, ChangeDetectorRef  } from '@angular/core';

@Component({
  selector: 'app-producto-informacion',
  templateUrl: './producto-informacion.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush
})

export class ProductoInformacionComponent implements OnInit {

  @Input() producto: any = {};
  public categorias: any = [];
  public subcategorias: any = [];
  public subcategRes: any = [];
  public proveedores: any = [];
  public usuario: any = {};
  public categoria: any = {};
  public bodegas: any = [];
  public medidas: any = [];
  public loading = false;
  public guardar = false;
  public variants: Array<{ nombre: string, cantidad: number }> = [];
  public tallas = ['x', 'm', 'l', 'xl'];
  public colores = ['azul', 'Amarillo', 'Blanco', 'Negro'];
  public materiales = ['Madera', 'Papel', 'Metal', 'Plastico', 'Vidrio'];

  constructor(
    private apiService: ApiService, private alertService: AlertService,
    private route: ActivatedRoute, private router: Router,
    private cdr: ChangeDetectorRef 
  ) {
    // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    this.addVariant();
  }

  ngOnInit(){
    this.usuario = this.apiService.auth_user();

    this.apiService.getAll('categorias/padre').subscribe(categorias => {
      this.categorias = categorias;
    }, error => { this.alertService.error(error); });
    
    this.apiService.getAll('subcategorias').subscribe(subcategorias => {
      this.subcategorias = subcategorias;
      this.subcategRes = this.subcategorias.filter((cat: any) => { return cat.id_cate_padre == this.producto.id_categoria; });
    }, error => { this.alertService.error(error); });

    this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);

    this.apiService.getAll('proveedores/list').subscribe(proveedores => {
      this.proveedores = proveedores;
      this.loading = false;
    }, error => { this.alertService.error(error); this.loading = false; });

  }

  public setCategoria(categoria: any) {
    this.loadCategorias();
    if (categoria.subcategoria) {
      this.subcategRes.push(categoria);
      this.producto.id_subcategoria = categoria.id;
      this.subcategorias.push(categoria);
    } else {
      this.categorias.push(categoria);
      this.producto.id_categoria = categoria.id;
    }

  }

  public opAvanzadas: boolean = false;

  toggleDiv(): void { this.opAvanzadas = !this.opAvanzadas; this.cdr.detectChanges(); }

  public setCompuesto() {
    if (this.producto.tipo == 'Producto') {
      this.producto.tipo = 'Compuesto';
    } else {
      this.producto.tipo = 'Producto';
    }
  }

  public calPrecioBase() {
    if (this.usuario.empresa.iva > 0) {
      this.producto.impuesto = this.usuario.empresa.iva / 100;
      this.producto.precio = (this.producto.precio_final / (1 + (this.producto.impuesto * 1))).toFixed(4);
    }
  }

  public calPrecioFinal() {
    if (this.usuario.empresa.iva > 0) {
      this.producto.impuesto = this.usuario.empresa.iva / 100;
      this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
    }
  }

  public onSubmit() {
    this.guardar = true;    
    this.apiService.store('producto', this.producto).subscribe(producto => {
      this.guardar = false;
      if (!this.producto.id) {
        this.producto = producto;
      }
      if (this.producto.tipo == 'Producto') {
        this.router.navigate(['/producto/editar/' + producto.id]);
        this.alertService.success("Producto guardado", 'El producto fue guardado exitosamente.');
      }
      if (this.producto.tipo == 'Servicio') {
        this.router.navigate(['/servicio/editar/' + producto.id]);
        this.alertService.success("Servicio guardado", 'El servicio fue guardado exitosamente.');
      }
      if (this.producto.tipo == 'Compuesto') {
        this.router.navigate(['/producto/editar/' + producto.id]);
        this.alertService.success("Producto compuesto guardado", 'El producto compuesto fue guardado exitosamente.');
      }
      if (this.producto.tipo == 'Materia Prima') {
        this.router.navigate(['/materias-prima/editar/' + producto.id]);
        this.alertService.success("Materia prima guardada", 'La materia prima fue guardada exitosamente.');
      }
    }, error => { this.alertService.error(error); this.guardar = false; });
  }

  public barcode() {
    var ventana = window.open(this.apiService.baseUrl + "/api/barcode/" + this.producto.barcode + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
  }

  public verificarSiExiste() {
    if (this.producto.nombre) {
      this.apiService.getAll('productos', { nombre: this.producto.nombre, estado: 1, }).subscribe(productos => {
        if (productos.data[0]) {
          this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
            'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' + this.apiService.appUrl + '/producto/editar/' + productos.data[0].id + '">Ver producto</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
          );
        }
        this.loading = false;
      }, error => { this.alertService.error(error); this.loading = false; });
    }
  }

  // creacion de sku
  private correlativo: number = 1; // Inicialmente, este sería el primer SKU

  public updateInputValue(event: any): void {

    const categoriaSeleccionada = this.categorias.find((c: any) => c.id == this.producto.id_categoria);

    const subcategoriaSeleccionada = this.subcategorias.find((s: any) => s.id == this.producto.id_subcategoria);

    this.subcategRes = this.subcategorias.filter((cat: any) => { return cat.id_cate_padre == event; });    

    this.producto.codigo = `${categoriaSeleccionada?.nombre ? categoriaSeleccionada?.nombre.slice(0, 3).toUpperCase() : ''}${subcategoriaSeleccionada?.nombre ? subcategoriaSeleccionada?.nombre.slice(0, 3).toUpperCase() : ''}${this.correlativo.toString().padStart(5, '0')}`;
  }
  //   variantes

  addVariant(): void { this.variants.push({ nombre: '', cantidad: 0 }); }

  removeVariant(index: number): void { this.variants.splice(index, 1); }

  addAttribute(event: string, tipo: string){
    switch(tipo) { 
      case 'talla': { 
         //statements; 
        if(event && !this.tallas.includes(event)) {
          this.tallas.push(event); // Agregar la nueva talla a la lista
        }
        this.producto.talla = event; // Establecer la talla seleccionada
         break; 
      } 
      case 'color': { 
         //statements; 
        if(event && !this.colores.includes(event)) {
          this.colores.push(event); // Agregar la nueva talla a la lista
        }
        this.producto.colores = event; // Establecer la talla seleccionada
         break; 
      } 
      case 'material': { 
         //statements; 
        if(event && !this.materiales.includes(event)){
          this.materiales.push(event); // Agregar la nueva talla a la lista
        }
        this.producto.material = event; // Establecer la talla seleccionada
         break; 
      } 
      default: { 
         //statements; 
         break; 
      } 
   }

  }

  private loadCategorias() {
    // Cargar categorías principales
    this.apiService.getAll('categorias/padre').subscribe(categorias => {
        this.categorias = categorias;
        
        // Si ya hay una categoría seleccionada, generar el código
        if (this.producto.id_categoria) {
            this.updateInputValue(this.producto.id_categoria);
        }
        this.cdr.detectChanges();
    }, error => { this.alertService.error(error); });
    
    // Cargar subcategorías
    this.apiService.getAll('subcategorias').subscribe(subcategorias => {
        this.subcategorias = subcategorias;
        if (this.producto.id_categoria) {
            this.subcategRes = this.subcategorias.filter((cat: any) => { 
                return cat.id_cate_padre == this.producto.id_categoria; 
            });
            
            // Si ya hay una subcategoría seleccionada, actualizar el código
            if (this.producto.id_subcategoria) {
                this.updateInputValue(this.producto.id_categoria);
            }
        }
        this.cdr.detectChanges();
    }, error => { this.alertService.error(error); });
}

}
