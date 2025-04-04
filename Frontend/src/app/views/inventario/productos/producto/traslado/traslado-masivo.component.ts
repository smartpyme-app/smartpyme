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

    public productos: any = [];
    public loading: boolean = false;
    public downloading: boolean = false;
    public saving: boolean = false;
    public filtros: any = {
        id_bodega_origen: '',
        id_bodega_destino: '',
        id_categoria: '',
        buscador: '',
        orden: 'nombre',
        direccion: 'asc',
        paginate: 100
    };
    public bodegas: any = [];
    public categorias: any = [];
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
        this.loadAll();

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
    }

    public initFilters() {
        this.filtros = {
            id_bodega_origen: '',
            id_bodega_destino: '',
            id_categoria: '',
            buscador: '',
            orden: 'nombre',
            direccion: 'asc',
            paginate: 100
        };
    }

    public loadAll() {
        const filtrosGuardados = localStorage.getItem('trasladoInventarioFiltros');
        if (filtrosGuardados) {
            this.filtros = JSON.parse(filtrosGuardados);
        } else {
            this.initFilters();
        }
        
        if (this.filtros.id_bodega_origen && this.filtros.id_bodega_destino) {
            this.filtrarProductos();
        }
    }

    public filtrarProductos() {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            return;
        }
        
        // No permitir la misma bodega como origen y destino
        if (this.filtros.id_bodega_origen === this.filtros.id_bodega_destino) {
            this.alertService.warning('La bodega origen y destino no pueden ser la misma', 'Error en selección');
            this.filtros.id_bodega_destino = '';
            return;
        }
        
        localStorage.setItem('trasladoInventarioFiltros', JSON.stringify(this.filtros));
        this.loading = true;
        this.seleccionados = [];
        this.productosParaTraslado = [];

        this.apiService.getAll('productos', { 
            ...this.filtros, 
            id_bodega: this.filtros.id_bodega_origen // Usamos el parámetro existente para compatibilidad
        }).subscribe(productos => { 
            this.productos = productos;
            
            // Limpiar y reconstruir el mapa de productos
            this.productosMap.clear();
            
            // Preparar los productos para el traslado
            if (this.productos?.data) {
                this.productos.data.forEach((producto: any) => {
                    // Guardamos en el mapa para acceso rápido
                    this.productosMap.set(producto.id, producto);
                    
                    producto.seleccionado = false;
                    producto.cantidad_traslado = 0;
                    
                    // Establecer stock en bodega origen
                    const inventarioOrigen = producto.inventarios.find(
                        (inv: any) => inv.id_bodega == this.filtros.id_bodega_origen
                    );
                    producto.stock_origen = inventarioOrigen ? inventarioOrigen.stock : 0;
                    producto.id_inventario_origen = inventarioOrigen ? inventarioOrigen.id : null;
                    
                    // Establecer stock en bodega destino
                    const inventarioDestino = producto.inventarios.find(
                        (inv: any) => inv.id_bodega == this.filtros.id_bodega_destino
                    );
                    producto.stock_destino = inventarioDestino ? inventarioDestino.stock : 0;
                    producto.id_inventario_destino = inventarioDestino ? inventarioDestino.id : null;
                });
            }
            
            this.loading = false;
            
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {
            this.alertService.error(error); 
            this.loading = false;
        });
    }

    public toggleSeleccion(producto: any) {
        producto.seleccionado = !producto.seleccionado;
        this.actualizarSeleccionados();
    }

    public seleccionarTodos(event: any) {
        const seleccionado = event.target.checked;
        if (this.productos.data) {
            this.productos.data.forEach((producto: any) => {
                producto.seleccionado = seleccionado;
            });
        }
        this.actualizarSeleccionados();
    }

    public actualizarSeleccionados() {
        this.seleccionados = this.productos.data ? 
            this.productos.data.filter((p: any) => p.seleccionado) : [];
        
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
            cantidad_traslado: p.cantidad_traslado
        }));
    }

    public validarCantidadTraslado(producto: any) {
        if (!producto.cantidad_traslado) {
            producto.cantidad_traslado = 0;
            return;
        }
        
        // No permitir valores negativos
        if (producto.cantidad_traslado < 0) {
            producto.cantidad_traslado = 0;
        }
        
        // No permitir trasladar más del stock disponible
        if (producto.cantidad_traslado > producto.stock_origen) {
            producto.cantidad_traslado = producto.stock_origen;
            this.alertService.warning('No puede trasladar más unidades que el stock disponible en la bodega origen.', 'Cantidad inválida');
        }
        
        this.actualizarProductosParaTraslado();
    }

    public getNombreProducto(idProducto: number): string {
        const producto = this.productosMap.get(idProducto);
        return producto ? producto.nombre : 'Producto no encontrado';
    }

    public getNombreBodega(idBodega: string): string {
        return this.bodegasMap.get(idBodega) || 'Bodega no encontrada';
    }

    public openModalConfirmar(template: TemplateRef<any>) {
        this.actualizarSeleccionados();
        
        if (this.seleccionados.length === 0) {
            this.alertService.warning('Debe seleccionar al menos un producto para realizar el traslado.', 'Sin productos seleccionados');
            return;
        }

        this.actualizarProductosParaTraslado();
        
        if (this.productosParaTraslado.length === 0) {
            this.alertService.warning('Debe especificar la cantidad a trasladar para al menos un producto.', 'Sin cantidades especificadas');
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
            detalle: this.trasladoInventario.detalle,
            id_bodega_origen: this.filtros.id_bodega_origen,
            id_bodega_destino: this.filtros.id_bodega_destino,
            productos: this.trasladoInventario.productos,
            id_usuario: this.apiService.auth_user().id,
            id_empresa: this.apiService.auth_user().id_empresa
        };

        this.apiService.store('productos/traslado-inventario', datos).subscribe(
            respuesta => {
                this.alertService.success('Traslado realizado', 'Se han trasladado ' + respuesta.trasladados + ' productos exitosamente.');
                this.modalRef.hide();
                this.saving = false;
                this.filtrarProductos();
            },
            error => {
                this.alertService.error(error);
                this.saving = false;
            }
        );
    }

    public exportarPlantilla() {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Debe seleccionar las bodegas origen y destino para exportar el listado.', 'Selección incompleta');
            return;
        }
        
        this.downloading = true;
        
        const filtrosExport = {
            ...this.filtros,
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

    public setPagination(event: any): void {
        this.loading = true;
        this.apiService.paginate(this.productos.path + '?page='+ event.page, {
            ...this.filtros,
            id_bodega: this.filtros.id_bodega_origen
        }).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {
            this.alertService.error(error); 
            this.loading = false;
        });
    }

    public openModalImportar(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    public importarAjustes(fileInput: HTMLInputElement, detalle: string) {
        if (!detalle) {
            this.alertService.warning('Debe proporcionar una descripción para el ajuste.','Descripción requerida');
            return;
        }
    
        if (!fileInput.files || fileInput.files.length === 0) {
            this.alertService.warning('Debe seleccionar un archivo para importar.', 'No se ha seleccionado ningún archivo.');
            return;
        }
    
        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('archivo', file);
        formData.append('detalle', detalle);
        formData.append('id_usuario', this.apiService.auth_user().id);
        formData.append('id_empresa', this.apiService.auth_user().id_empresa);
    
        this.saving = true;
        this.apiService.upload('productos/ajuste-masivo/importar', formData).subscribe(
            respuesta => {
                this.alertService.success('Ajuste masivo importado', ' productos exitosamente.');
                this.modalRef.hide();
                this.saving = false;
                this.loadAll();
            },
            error => {
                this.alertService.error(error);
                this.saving = false;
            }
        );
    }
}