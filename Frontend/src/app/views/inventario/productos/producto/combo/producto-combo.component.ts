import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';
import { SumPipe }     from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-combo',
  templateUrl: './producto-combo.component.html',
  providers: [ SumPipe ]
})
export class ProductoComboComponent implements OnInit {

    public producto: any = {};
    public categorias:any = [];
    public subcategorias:any = [];
    public subcategRes:any = [];
    public proveedores:any = [];
    public usuario:any = {};
    public categoria:any = {};
    public bodegas:any = [];
    public medidas:any = [];
    public loading = false;
    public guardar = false;
    public variants: Array<{ nombre: string, cantidad: number }> = [];
    public tallas = ['x', 'm', 'l', 'xl'];
    public colores = ['azul', 'Amarillo', 'Blanco', 'Negro'];
    public materiales = ['Madera', 'Papel', 'Metal', 'Plastico', 'Vidrio'];


    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private sumPipe:SumPipe,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
        this.addVariant();
    }

    ngOnInit() {
        this.producto.nombre="";
        this.producto.detalles=[];
        this.producto.id_empresa = this.apiService.auth_user().id_empresa;
        this.producto.id_categoria = 3154;
        this.producto.tipo ='Compuesto';
        this.usuario = this.apiService.auth_user();
        // subcategorias
        
        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('subcategorias').subscribe(subcategorias => {
            this.subcategorias = subcategorias;
        }, error => {this.alertService.error(error);});

        this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);
        
        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error);});

    }

    public setCategoria(categoria:any){
        if(categoria.subcategoria){
            this.subcategRes.push(categoria);
            this.producto.id_subcategoria= categoria.id;

        }else{
            this.categorias.push(categoria);
            this.producto.id_categoria = categoria.id;
        }

    }

    public setCompuesto(){
        if(this.producto.tipo == 'Producto'){
            this.producto.tipo = 'Compuesto';
        }else{
            this.producto.tipo = 'Producto';
        }
    }

    // CALCULO DEL STOCK MULTIPLICADO
    public calCantidadenCombo(){
        if(this.producto.stock > 0){
            this.producto.detalles.forEach((detalle: any) => {
                detalle.cantidad= detalle.cantidad * this.producto.stock;
              });
        }
    }

    public calPrecioFinal(){
        if(this.usuario.empresa.iva > 0){
            this.producto.impuesto = this.usuario.empresa.iva / 100;
            this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
        }
    }


    public onSubmit() {
        this.guardar = true;
        this.producto.codigo="CMPKIT"+this.producto.codigo;
        this.apiService.store('producto', this.producto).subscribe(producto => {
            this.guardar = false;
            if(!this.producto.id) {
                this.producto = producto;
            }
            if(this.producto.tipo == 'Producto'){
                this.router.navigate(['/producto/editar/' + producto.id]);
                this.alertService.success("Producto guardado", 'El producto fue guardado exitosamente.');
            }
            if(this.producto.tipo == 'Servicio'){
                this.router.navigate(['/servicio/editar/' + producto.id]);
                this.alertService.success("Servicio guardado", 'El servicio fue guardado exitosamente.');
            }
            if(this.producto.tipo == 'Compuesto'){
                this.router.navigate(['/producto/editar/' + producto.id]);
                this.alertService.success("Producto compuesto guardado", 'El producto compuesto fue guardado exitosamente.');
            }
            if(this.producto.tipo == 'Materia Prima'){
                this.router.navigate(['/materias-prima/editar/' + producto.id]);
                this.alertService.success("Materia prima guardada", 'La materia prima fue guardada exitosamente.');
            }
        },error => {this.alertService.error(error); this.guardar = false; });
    }

    public barcode(){
        var ventana = window.open(this.apiService.baseUrl + "/api/barcode/" + this.producto.codigo + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    public verificarSiExiste(){
        if(this.producto.nombre){
            this.apiService.getAll('productos', { nombre: this.producto.nombre, estado: 1, }).subscribe(productos => { 
                if(productos.data[0]){
                    this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.', 
                        'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' + this.apiService.appUrl + '/producto/editar/' + productos.data[0].id + '">Ver producto</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
                    );
                }
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }
    }

    public sumTotal() {
        this.producto.costo = (parseFloat(this.sumPipe.transform(this.producto.detalles, 'total'))).toFixed(2);
    }

    public updatecompra(producto:any) {
        this.producto = producto;
        this.sumTotal();
    }

    // creacion de sku 
private correlativo: number = 1; // Inicialmente, este sería el primer SKU

 public updateInputValue(): void {
    const categoriaSeleccionada = this.categorias.find((c:any) => c.id === this.producto.id_categoria);
    const subcategoriaSeleccionada = this.subcategorias.find((s:any) => s.id === this.producto.id_subcategoria); 
    // console.log( this.producto.id_subcategoria);

    let nombreCategoria = '';
    let nombreSubcategoria = '';

    if (categoriaSeleccionada) {

        this.subcategRes = this.subcategorias.filter((subcategoria:any) => subcategoria.id_cate_padre === categoriaSeleccionada.id);
        nombreCategoria = categoriaSeleccionada.nombre.slice(0, 3).toUpperCase();
        console.log("Este es el action de la categoria");
        console.log(this.subcategRes);
        
    }

    if (subcategoriaSeleccionada) {
      nombreSubcategoria = subcategoriaSeleccionada.nombre.slice(0, 3).toUpperCase();
      console.log("Este es el action de la subcategoria");
      console.log(nombreSubcategoria);
    }

    if (this.producto.id_categoria && this.producto.id_subcategoria) {
      this.producto.codigo= `${nombreCategoria}${nombreSubcategoria}${this.correlativo.toString().padStart(5, '0')}`;
    }
  }

//   variantes 

addVariant(): void {
    this.variants.push({ nombre: '', cantidad: 0 });
  }

  removeVariant(index: number): void {
    this.variants.splice(index, 1);
  }
    
  
public agregarNuevaTalla(nombre: string) {
    if (nombre && !this.tallas.includes(nombre)) {
      this.tallas.push(nombre); // Agregar la nueva talla a la lista
    }
    this.producto.talla = nombre; // Establecer la talla seleccionada
  }

public agregarNuevoColor(nombre: string) {
    if (nombre && !this.colores.includes(nombre)) {
      this.colores.push(nombre); // Agregar la nueva talla a la lista
    }
    this.producto.colores = nombre; // Establecer la talla seleccionada
  }

public agregarNuevoMaterial(nombre: string) {
  if (nombre && !this.materiales.includes(nombre)) {
    this.materiales.push(nombre); // Agregar la nueva talla a la lista
  }
  this.producto.material = nombre; // Establecer la talla seleccionada
}

}
