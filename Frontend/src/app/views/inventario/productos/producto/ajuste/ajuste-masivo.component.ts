import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SumPipe } from '@pipes/sum.pipe';

@Component({
  selector: 'app-ajuste-masivo',
  templateUrl: './ajuste-masivo.component.html',
})
export class AjusteMasivoComponent implements OnInit {

    public productos: any = [];
    public loading: boolean = false;
    public downloading: boolean = false;
    public saving: boolean = false;
    public filtros: any = {};
    public bodegas: any = [];
    public categorias: any = [];
    public seleccionados: any[] = [];
    public ajusteMasivo: any = {
        detalle: '',
        productos: []
    };
    
    // Mapa para acceso rápido a productos por ID
    public productosMap: Map<number, any> = new Map();

    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService,
        private sumPipe: SumPipe
    ) {}

    ngOnInit() {
       // this.initFilters();
        this.loadAll();

        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bodegas/list').subscribe(bodegas => { 
            console.log('bodegas', bodegas);
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error);});
    }

    public initFilters() {
        this.filtros = {
            id_bodega: '',
            id_categoria: '',
            buscador: '',
            orden: 'nombre',
            direccion: 'asc',
            paginate: 100  // Mostrar más productos por página para ajuste masivo
        };
    }

    public loadAll() {
        const filtrosGuardados = localStorage.getItem('ajusteMasivoFiltros');
        if (filtrosGuardados) {
            this.filtros = JSON.parse(filtrosGuardados);
        } else {
            this.initFilters();
        }
        this.filtrarProductos();
    }

    public filtrarProductos() {
        localStorage.setItem('ajusteMasivoFiltros', JSON.stringify(this.filtros));
        this.loading = true;
        this.seleccionados = [];
        this.ajusteMasivo.productos = [];

        this.apiService.getAll('productos', this.filtros).subscribe(productos => { 
            this.productos = productos;
            
            // Limpiar y reconstruir el mapa de productos
            this.productosMap.clear();
            
            // Preparar los productos para el ajuste masivo
            if (this.productos?.data) {
                this.productos.data.forEach((producto: any) => {
                    // Guardamos en el mapa para acceso rápido
                    this.productosMap.set(producto.id, producto);
                    
                    producto.seleccionado = false;
                    producto.stock_nuevo = null;
                    producto.diferencia = 0;
                    
                    // Si hay un filtro de bodega, establecer el inventario actual
                    if (this.filtros.id_bodega) {
                        const inventario = producto.inventarios.find((inv: any) => inv.id_bodega == this.filtros.id_bodega);
                        if (inventario) {
                            producto.inventario_actual = inventario;
                            producto.stock_actual = inventario.stock;
                            producto.stock_nuevo = inventario.stock;
                        }
                    }
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

    // public toggleSeleccion(producto: any, event?: Event) {
    //     if (event) {
    //         event.stopPropagation();
    //     }
    //     producto.seleccionado = !producto.seleccionado;
        
    //     this.actualizarSeleccionados();
    // }
    public toggleSeleccion(producto: any) {
        producto.seleccionado = !producto.seleccionado;
        this.actualizarSeleccionados();
    }

    public seleccionarTodos(event: any) {
        const seleccionado = event.target.checked;
        this.productos.data.forEach((producto: any) => {
            producto.seleccionado = seleccionado;
        });
        this.actualizarSeleccionados();
    }

    public actualizarSeleccionados() {
        this.seleccionados = this.productos.data.filter((p: any) => p.seleccionado);
    }

    public calcularDiferencia(producto: any) {
        const stockActual = parseFloat(producto.stock_actual) || 0;
        const stockNuevo = parseFloat(producto.stock_nuevo) || 0;
        producto.diferencia = stockNuevo - stockActual;
        return producto.diferencia;
    }

    // Método auxiliar para obtener el nombre del producto por ID (para el modal de confirmación)
    public getNombreProducto(idProducto: number): string {
        const producto = this.productosMap.get(idProducto);
        return producto ? producto.nombre : 'Producto no encontrado';
    }

    // Método para obtener la clase CSS según la diferencia
    public getDiferenciaClass(diferencia: number): string {
        return diferencia > 0 ? 'text-success' : (diferencia < 0 ? 'text-danger' : '');
    }

    public openModalConfirmar(template: TemplateRef<any>) {
        // Verificar que haya productos seleccionados
        this.actualizarSeleccionados();
        if (this.seleccionados.length === 0) {
            this.alertService.warning('Debe seleccionar al menos un producto para realizar el ajuste masivo.','Sin productos seleccionados');
            return;
        }

        // Verificar que al menos un producto tenga cambios
        const conCambios = this.seleccionados.some(p => p.diferencia !== 0);
        if (!conCambios) {
            this.alertService.warning('Ninguno de los productos seleccionados tiene cambios de stock.','Sin cambios');
            return;
        }

        // Preparar los datos para el ajuste
        this.ajusteMasivo.productos = this.seleccionados.filter(p => p.diferencia !== 0).map(p => ({
            id_producto: p.id,
            id_bodega: p.inventario_actual?.id_bodega,
            stock_actual: p.stock_actual,
            stock_nuevo: p.stock_nuevo,
            diferencia: p.diferencia
        }));

        // Abrir modal de confirmación
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public guardarAjusteMasivo() {
        if (!this.ajusteMasivo.detalle) {
            this.alertService.warning('Debe proporcionar una descripción para el ajuste.','Descripción requerida');
            return;
        }

        this.saving = true;
        
        const datos = {
            detalle: this.ajusteMasivo.detalle,
            productos: this.ajusteMasivo.productos,
            id_usuario: this.apiService.auth_user().id,
            id_empresa: this.apiService.auth_user().id_empresa
        };

        this.apiService.store('ajuste-masivo', datos).subscribe(
            respuesta => {
                this.alertService.success('Ajuste masivo realizado', 'Se han actualizado ' + respuesta.actualizados + ' productos exitosamente.');
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

    public exportarPlantilla() {
        this.downloading = true;
        
        // Agregar ID de bodega a los filtros si existe
        const filtrosExport = {...this.filtros, formato: 'plantilla'};
        
        this.apiService.export('productos/exportar-plantilla', filtrosExport).subscribe(
            (data: Blob) => {
                const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'plantilla_ajuste_inventario.xlsx';
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

    public setPagination(event: any): void {
        this.loading = true;
        this.apiService.paginate(this.productos.path + '?page='+ event.page, this.filtros).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }
}