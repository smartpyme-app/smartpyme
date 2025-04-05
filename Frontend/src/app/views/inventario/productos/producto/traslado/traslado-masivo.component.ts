import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SumPipe } from '@pipes/sum.pipe';

@Component({
    selector: 'app-traslado-masivo',
    templateUrl: './traslado-masivo.component.html',
})
export class TrasladoMasivoComponent implements OnInit {
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

    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService,
        private sumPipe: SumPipe
    ) {}

    ngOnInit() {
        this.loadData();
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
                this.resultadosBusqueda = [];
                this.filtros.buscador = '';
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

    public buscarProducto() {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Debe seleccionar las bodegas de origen y destino primero.', 'Bodegas requeridas');
            return;
        }

        if (!this.filtros.buscador || this.filtros.buscador.trim().length < 2) {
            this.alertService.warning('Ingrese al menos 2 caracteres para buscar.', 'Búsqueda requerida');
            return;
        }

        this.loading = true;
        this.resultadosBusqueda = [];

        this.apiService.getAll('productos', { 
            buscador: this.filtros.buscador.trim(),
            id_bodega_origen: this.filtros.id_bodega_origen,
            id_bodega_destino: this.filtros.id_bodega_destino,
            paginate: 10
        }).subscribe(
            response => { 
                if (response.data && response.data.length > 0) {
                    this.resultadosBusqueda = response.data.map((producto: any) => {
                        // Obtener stock en bodega origen
                        const inventarioOrigen = producto.inventarios.find(
                            (inv: any) => inv.id_bodega == this.filtros.id_bodega_origen
                        );
                        const stockOrigen = inventarioOrigen ? inventarioOrigen.stock : 0;
                        const idInventarioOrigen = inventarioOrigen ? inventarioOrigen.id : null;
                        
                        // Obtener stock en bodega destino
                        const inventarioDestino = producto.inventarios.find(
                            (inv: any) => inv.id_bodega == this.filtros.id_bodega_destino
                        );
                        const stockDestino = inventarioDestino ? inventarioDestino.stock : 0;
                        const idInventarioDestino = inventarioDestino ? inventarioDestino.id : null;

                        return {
                            ...producto,
                            stock_origen: stockOrigen,
                            stock_destino: stockDestino,
                            id_inventario_origen: idInventarioOrigen,
                            id_inventario_destino: idInventarioDestino,
                            cantidad_traslado: 0
                        };
                    });
                } else {
                    this.alertService.info('No se encontraron productos con ese criterio de búsqueda.', 'Sin resultados');
                }
                this.loading = false;
            },
            error => {
                this.alertService.error(error); 
                this.loading = false;
            }
        );
    }

    public productoYaSeleccionado(idProducto: number): boolean {
        return this.seleccionados.some(p => p.id === idProducto);
    }

    public agregarProducto(producto: any) {
        if (this.productoYaSeleccionado(producto.id)) {
            this.alertService.info('Este producto ya está en la lista de traslado.', 'Producto duplicado');
            return;
        }

        if (producto.stock_origen <= 0) {
            this.alertService.warning('Este producto no tiene stock disponible en la bodega de origen.', 'Sin stock');
            return;
        }

        // Clonamos el producto para evitar referencias
        const productoTraslado = { ...producto };
        productoTraslado.cantidad_traslado = 1; // Por defecto 1
        
        this.seleccionados.push(productoTraslado);
        this.actualizarProductosParaTraslado();
    }

    public quitarProducto(producto: any) {
        this.seleccionados = this.seleccionados.filter(p => p.id !== producto.id);
        this.actualizarProductosParaTraslado();
    }

    public limpiarSeleccion() {
        if (confirm('¿Está seguro de eliminar todos los productos de la lista de traslado?')) {
            this.seleccionados = [];
            this.productosParaTraslado = [];
        }
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
                this.resultadosBusqueda = [];
                this.filtros.buscador = '';
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
            respuesta => {
                //this.alertService.success('Traslado masivo importado', 'Se han trasladado ' + respuesta.trasladados + ' productos exitosamente.');
                this.modalRef.hide();
                this.saving = false;
                
                // Limpiar productos seleccionados
                this.seleccionados = [];
                this.productosParaTraslado = [];
                this.resultadosBusqueda = [];
                this.filtros.buscador = '';
                
                // Si hay errores, mostrarlos
                // if (respuesta.errores && respuesta.errores.length > 0) {
                //     let mensajeErrores = 'Los siguientes productos no pudieron ser trasladados:<br>';
                //     respuesta.errores.forEach((error: string) => {
                //         mensajeErrores += '- ' + error + '<br>';
                //     });
                //     this.alertService.warning(mensajeErrores, 'Advertencias durante el traslado');
                // }
            },
            error => {
                this.alertService.error(error);
                this.saving = false;
            }
        );
    }
}