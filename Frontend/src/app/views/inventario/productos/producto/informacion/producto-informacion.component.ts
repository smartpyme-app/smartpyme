import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-informacion',
  templateUrl: './producto-informacion.component.html'
})
export class ProductoInformacionComponent implements OnInit {

    @Input() producto: any = {};
    public categorias:any = [];
    public proveedores:any = [];
    public usuario:any = {};
    public categoria:any = {};
    public bodegas:any = [];
    public medidas:any = [];
    public loading = false;
    public guardar = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        
        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);
        
        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public setCategoria(categoria:any){
        this.categorias.push(categoria);
        this.producto.id_categoria = categoria.id;
    }

    public setCompuesto(){
        if(this.producto.tipo == 'Producto'){
            this.producto.tipo = 'Compuesto';
        }else{
            this.producto.tipo = 'Producto';
        }
    }

    public calPrecioBase(){
        if(this.usuario.empresa.iva > 0){
            this.producto.impuesto = this.usuario.empresa.iva / 100;
            this.producto.precio = (this.producto.precio_final / (1 + (this.producto.impuesto * 1))).toFixed(4);
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

    // creacion de sku 


  // Método para actualizar el inputValue cuando cambia el select
//   public updateInputValue(value: string): void {

//     console.log(value)
//     // this.producto.codigo = value;

//     const selectedCategory = this.categorias.find((catego:any) => catego.id === value);
//     this.producto.codigo = selectedCategory ? selectedCategory.nombre.substring(0, 3) : '';
//   }
private correlativo: number = 1; // Inicialmente, este sería el primer SKU

 public updateInputValue(): void {
    const categoriaSeleccionada = this.categorias.find((c:any) => c.id === this.producto.id_categoria);
    const subcategoriaSeleccionada = this.categorias.find((s:any) => s.id === this.producto.id_subcategoria);

    let nombreCategoria = '';
    let nombreSubcategoria = '';

    if (categoriaSeleccionada) {
      nombreCategoria = categoriaSeleccionada.nombre.slice(0, 3).toUpperCase();
    }

    if (subcategoriaSeleccionada) {
      nombreSubcategoria = subcategoriaSeleccionada.nombre.slice(0, 3).toUpperCase();
    }

    if (this.producto.id_categoria && this.producto.id_subcategoria) {
      this.producto.codigo= `${nombreCategoria}${nombreSubcategoria}${this.correlativo.toString().padStart(5, '0')}`;
    }
  }
    

}
