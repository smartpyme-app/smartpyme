import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SumPipe } from '@pipes/sum.pipe';
import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-traslado-masivo',
    templateUrl: './traslado-masivo.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule],
    
})
export class TrasladoMasivoComponent implements OnInit {
    public productos: any = [];
    public resultadosBusqueda: any[] = [];
    public loading: boolean = false;
    public downloading: boolean = false;
    public saving: boolean = false;
    public filtros: any = {
        id_bodega_origen: '',
        id_bodega_destino: '',
        buscador: '',
        orden: 'nombre',
        direccion: 'asc',
        paginate: 10
    };
    public traslado: any = {
        fecha: this.apiService.date(),
        id_usuario: '',
    };
    public bodegas: any = [];
    public categorias: any = [];
    public usuarios: any = [];
    public seleccionados: any[] = [];
    public productosParaTraslado: any[] = [];
    public trasladoInventario: any = {
        detalle: '',
        productos: []
    };
    
    public productosMap: Map<number, any> = new Map();
    public bodegasMap: Map<string, string> = new Map();
    private tieneShopify: boolean = false;
    
    // Control para el buscador
    searchControl = new FormControl();

    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService,
        private sumPipe: SumPipe
    ) {}

    ngOnInit() {
        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;
        
        this.loadData();
        
        // Configurar el buscador con debounce
        this.searchControl.valueChanges
          .pipe(
            debounceTime(500),
            filter((query: string) => query?.trim().length > 0),
            switchMap((query: any) => {
              this.loading = true;
              // Construir la URL con los parámetros de búsqueda
              const params = {
                query: query.trim(),
                id_bodega_origen: this.filtros.id_bodega_origen,
                id_bodega_destino: this.filtros.id_bodega_destino
              };
              return this.apiService.getAll(`productos/buscar-by-query`, params).pipe(
                catchError(error => {
                  console.error('Error en la búsqueda:', error);
                  this.productos = [];
                  this.loading = false;
                  return of([]);
                })
              );
            })
          )
          .subscribe({
            next: (results: any[]) => {
              this.productos = Array.isArray(results) ? results : [];
              this.procesarProductosEncontrados();
              this.loading = false;
            },
            error: (err) => {
              console.error('Error no controlado:', err);
              this.loading = false;
            }
          });
    }
    
    // Procesar los productos encontrados con información de stock
    private procesarProductosEncontrados() {
        this.productos = this.productos.map((producto: any) => {
            // Obtener stock en bodega origen
            const inventarioOrigen = producto.inventarios.find(
                (inv: any) => inv.id_bodega == this.filtros.id_bodega_origen
            );
            const stockOrigen = inventarioOrigen ? inventarioOrigen.stock : 0;
            
            // Obtener stock en bodega destino
            const inventarioDestino = producto.inventarios.find(
                (inv: any) => inv.id_bodega == this.filtros.id_bodega_destino
            );
            const stockDestino = inventarioDestino ? inventarioDestino.stock : 0;
            
            return {
                ...producto,
                stock_origen: stockOrigen,
                stock_destino: stockDestino
            };
        });
    }

    public getStockOrigen(producto: any): number {
        if (producto.stock_origen !== undefined) {
            return producto.stock_origen;
        }
        
        const inventarioOrigen = producto.inventarios.find(
            (inv: any) => inv.id_bodega == this.filtros.id_bodega_origen
        );
        return inventarioOrigen ? inventarioOrigen.stock : 0;
    }
    
    public getStockDestino(producto: any): number {
        if (producto.stock_destino !== undefined) {
            return producto.stock_destino;
        }
        
        const inventarioDestino = producto.inventarios.find(
            (inv: any) => inv.id_bodega == this.filtros.id_bodega_destino
        );
        return inventarioDestino ? inventarioDestino.stock : 0;
    }

    public loadData() {
        const filtrosGuardados = localStorage.getItem('trasladoInventarioFiltros');
        if (filtrosGuardados) {
            this.filtros = JSON.parse(filtrosGuardados);
        }
        
        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bodegas/list').subscribe(bodegas => { 
            this.bodegas = bodegas;
            
            // Crear un mapa para acceso rápido a nombres de bodegas
            this.bodegas.forEach((bodega: any) => {
                this.bodegasMap.set(bodega.id.toString(), bodega.nombre);
            });
            
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
            if (this.apiService.auth_user().tipo != 'Administrador' && this.apiService.auth_user().tipo != 'Supervisor') {
                this.usuarios = this.usuarios.filter((item: any) => item.id == this.apiService.auth_user().id);
            }
            this.traslado.id_usuario = this.apiService.auth_user().id;
        }, error => {
            this.alertService.error(error);
        });
    }

    public cambiarBodegaOrigen() {
        // Si se cambia la bodega origen, limpiamos los productos seleccionados
        if (this.seleccionados.length > 0) {
            if (confirm('Al cambiar la bodega de origen se perderá la lista actual de productos a trasladar. ¿Desea continuar?')) {
                this.seleccionados = [];
                this.productosParaTraslado = [];
                this.productos = [];
                this.searchControl.setValue('');
                this.guardarFiltros();
            } else {
                // Restaurar bodega origen anterior
                const filtrosGuardados = localStorage.getItem('trasladoInventarioFiltros');
                if (filtrosGuardados) {
                    const filtrosAnteriores = JSON.parse(filtrosGuardados);
                    this.filtros.id_bodega_origen = filtrosAnteriores.id_bodega_origen;
                }
            }
        } else {
            this.guardarFiltros();
        }
    }

    public cambiarBodegaDestino() {
        // Validar que no sea la misma bodega origen
        if (this.filtros.id_bodega_origen === this.filtros.id_bodega_destino) {
            this.alertService.warning('No puede seleccionar la misma bodega como origen y destino.', 'Bodega duplicada');
            this.filtros.id_bodega_destino = '';
            return;
        }
        
        this.guardarFiltros();
    }

    private guardarFiltros() {
        localStorage.setItem('trasladoInventarioFiltros', JSON.stringify(this.filtros));
    }

    public selectProducto(producto: any) {
        if (this.productoYaSeleccionado(producto.id)) {
            this.alertService.info('Este producto ya está en la lista de traslado.', 'Producto duplicado');
            this.searchControl.setValue('');
            this.productos = [];
            return;
        }

        const stockOrigen = this.getStockOrigen(producto);
        if (stockOrigen <= 0) {
            this.alertService.warning('Este producto no tiene stock disponible en la bodega de origen.', 'Sin stock');
            this.searchControl.setValue('');
            this.productos = [];
            return;
        }

        // Preparar inventario origen y destino
        const inventarioOrigen = producto.inventarios.find(
            (inv: any) => inv.id_bodega == this.filtros.id_bodega_origen
        );
        const inventarioDestino = producto.inventarios.find(
            (inv: any) => inv.id_bodega == this.filtros.id_bodega_destino
        );

        // Crear objeto de producto para el traslado
        const productoTraslado = {
            id: producto.id,
            nombre: this.getNombreCompleto(producto),
            nombre_categoria: producto.nombre_categoria,
            img: producto.img,
            stock_origen: stockOrigen,
            stock_destino: this.getStockDestino(producto),
            id_inventario_origen: inventarioOrigen ? inventarioOrigen.id : null,
            id_inventario_destino: inventarioDestino ? inventarioDestino.id : null,
            cantidad_traslado: 1 // Por defecto 1
        };
        
        // Agregar a la lista de seleccionados
        this.seleccionados.push(productoTraslado);
        this.actualizarProductosParaTraslado();
        
        // Limpiar buscador
        this.searchControl.setValue('');
        this.productos = [];
    }

    public productoYaSeleccionado(idProducto: number): boolean {
        return this.seleccionados.some(p => p.id === idProducto);
    }

    public quitarProducto(producto: any) {
        this.seleccionados = this.seleccionados.filter(p => p.id !== producto.id);
        this.actualizarProductosParaTraslado();
    }

    public limpiarSeleccion() {


        Swal.fire({
            title: '¿Está seguro de eliminar todos los productos de la lista de traslado?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.isConfirmed) {
                this.seleccionados = [];
                this.productosParaTraslado = [];
            }
        });
    }

    public validarCantidadTraslado(producto: any) {
        if (!producto.cantidad_traslado || producto.cantidad_traslado < 1) {
            producto.cantidad_traslado = 1;
        }
        
        // No permitir trasladar más del stock disponible
        if (producto.cantidad_traslado > producto.stock_origen) {
            producto.cantidad_traslado = producto.stock_origen;
            this.alertService.warning('No puede trasladar más unidades que el stock disponible en la bodega origen.', 'Cantidad inválida');
        }
        
        this.actualizarProductosParaTraslado();
    }

    public actualizarProductosParaTraslado() {
        this.productosParaTraslado = this.seleccionados.filter(
            p => p.cantidad_traslado > 0 && p.stock_origen >= p.cantidad_traslado
        ).map(p => ({
            id_producto: p.id,
            id_inventario_origen: p.id_inventario_origen,
            id_inventario_destino: p.id_inventario_destino,
            id_bodega_origen: this.filtros.id_bodega_origen,
            id_bodega_destino: this.filtros.id_bodega_destino,
            stock_origen: p.stock_origen,
            stock_destino: p.stock_destino,
            cantidad_traslado: p.cantidad_traslado,
            nombre: p.nombre // Para mostrar en el modal de confirmación
        }));
    }

    public getNombreBodega(idBodega: string): string {
        return this.bodegasMap.get(idBodega) || 'Bodega no encontrada';
    }

    public getNombreProducto(idProducto: number): string {
        const producto = this.seleccionados.find(p => p.id === idProducto);
        return producto ? producto.nombre : 'Producto no encontrado';
    }

    public openModalConfirmar(template: TemplateRef<any>) {
        this.actualizarProductosParaTraslado();
        
        if (!this.trasladoInventario.detalle) {
            this.alertService.warning('Debe proporcionar una descripción para el traslado.', 'Concepto requerido');
            return;
        }
        
        if (this.productosParaTraslado.length === 0) {
            this.alertService.warning('Debe agregar al menos un producto con cantidad a trasladar mayor a cero.', 'Sin productos para trasladar');
            return;
        }

        this.trasladoInventario.productos = this.productosParaTraslado;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public realizarTraslado() {
        if (!this.trasladoInventario.detalle) {
            this.alertService.warning('Debe proporcionar una descripción para el traslado.', 'Descripción requerida');
            return;
        }

        this.saving = true;
        
        const datos = {
            concepto: this.trasladoInventario.detalle,
            id_bodega_origen: this.filtros.id_bodega_origen,
            id_bodega_destino: this.filtros.id_bodega_destino,
            id_usuario: this.traslado.id_usuario || this.apiService.auth_user().id,
            productos: this.productosParaTraslado.map(p => ({
                id_producto: p.id_producto,
                cantidad: p.cantidad_traslado
            }))
        };

        this.apiService.store('productos/traslado-masivo', datos).subscribe(
            respuesta => {
                this.alertService.success('Traslado realizado exitosamente', 'Se han trasladado ' + respuesta.trasladados + ' productos.');
                this.modalRef.hide();
                this.saving = false;
                
                // Limpiar productos seleccionados
                this.seleccionados = [];
                this.productosParaTraslado = [];
                this.productos = [];
                this.searchControl.setValue('');
            },
            error => {
                this.alertService.error(error);
                this.saving = false;
            }
        );
    }

    public exportarPlantilla() {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Debe seleccionar las bodegas origen y destino para exportar la lista.', 'Bodegas requeridas');
            return;
        }
        
        if (this.seleccionados.length === 0) {
            this.alertService.warning('No hay productos en la lista para exportar.', 'Sin productos');
            return;
        }
        
        this.downloading = true;
        
        const productosIds = this.seleccionados.map(p => p.id).join(',');
        
        const filtrosExport = {
            id_bodega_origen: this.filtros.id_bodega_origen,
            id_bodega_destino: this.filtros.id_bodega_destino,
            productos_ids: productosIds,
            formato: 'excel'
        };
        
        this.apiService.export('productos/exportar-traslado', filtrosExport).subscribe(
            (data: Blob) => {
                const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'traslado_inventario.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.downloading = false;
            }, 
            (error) => { 
                this.alertService.error(error); 
                this.downloading = false; 
            }
        );
    }

    public openModalImportar(template: TemplateRef<any>) {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Debe seleccionar las bodegas origen y destino primero.', 'Bodegas requeridas');
            return;
        }
        
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    public importarTraslado(fileInput: HTMLInputElement, detalle: string) {
        if (!detalle) {
            this.alertService.warning('Debe proporcionar una descripción para el traslado.', 'Descripción requerida');
            return;
        }
    
        if (!fileInput.files || fileInput.files.length === 0) {
            this.alertService.warning('Debe seleccionar un archivo para importar.', 'No se ha seleccionado ningún archivo.');
            return;
        }
    
        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('archivo', file);
        formData.append('concepto', detalle);
        formData.append('id_bodega_origen', this.filtros.id_bodega_origen);
        formData.append('id_bodega_destino', this.filtros.id_bodega_destino);
        formData.append('id_usuario', this.traslado.id_usuario || this.apiService.auth_user().id);
    
        this.saving = true;
        this.apiService.upload('productos/traslado-masivo/importar', formData).subscribe(
            (respuesta: any) => {
                this.alertService.success('Traslado masivo importado', 'Se han trasladado ' + respuesta.trasladados + ' productos exitosamente.');
                this.modalRef.hide();
                this.saving = false;
                this.seleccionados = [];
                this.productosParaTraslado = [];
                this.productos = [];
                this.searchControl.setValue('');
                
                //Si hay errores, mostrarlos
                if (respuesta.errores && respuesta.errores.length > 0) {
                    let mensajeErrores = 'Los siguientes productos no pudieron ser trasladados:<br>';
                    respuesta.errores.forEach((error: string) => {
                        mensajeErrores += '- ' + error + '<br>';
                    });
                    this.alertService.warning(mensajeErrores, 'Advertencias durante el traslado');
                }
            },
            error => {
            this.alertService.error(error);
            this.saving = false;
        }
    );
    }

    /**
     * Obtiene el nombre completo del producto (nombre + nombre_variante si aplica)
     */
    getNombreCompleto(producto: any): string {
        if (this.tieneShopify && producto.nombre_variante) {
            return `${producto.nombre} ${producto.nombre_variante}`;
        }
        return producto.nombre;
    }
}