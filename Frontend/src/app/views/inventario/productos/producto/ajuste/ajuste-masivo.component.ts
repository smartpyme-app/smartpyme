import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { SumPipe } from '@pipes/sum.pipe';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

@Component({
    selector: 'app-ajuste-masivo',
    templateUrl: './ajuste-masivo.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class AjusteMasivoComponent extends BasePaginatedComponent implements OnInit {

    public productos: PaginatedResponse<any> = {} as PaginatedResponse;
    public downloading: boolean = false;
    public saving: boolean = false;
    public bodegas: any = [];
    public categorias: any = [];
    public seleccionados: any[] = [];
    public ajusteMasivo: any = {
        detalle: '',
        productos: []
    };
    
    public productosMap: Map<number, any> = new Map();
    public bodegaSeleccionada: any = null;
    private tieneShopify: boolean = false;

    modalRef!: BsModalRef;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        private modalService: BsModalService,
        private sumPipe: SumPipe
    ) {
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.productos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.productos = data;
    }

    protected override onPaginateSuccess(response: PaginatedResponse): void {
        // Procesar productos recibidos después de paginar
        this.procesarProductosRecibidos();
    }

    ngOnInit() {
        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

       // this.initFilters();
        this.loadAll();

        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('bodegas/list').subscribe(bodegas => { 
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
            paginate: 100 
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
            this.procesarProductosRecibidos();
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

    public getNombreProducto(idProducto: number): string {
        const producto = this.productosMap.get(idProducto);
        return producto ? this.getNombreCompleto(producto) : 'Producto no encontrado';
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

    public getDiferenciaClass(diferencia: number): string {
        return diferencia > 0 ? 'text-success' : (diferencia < 0 ? 'text-danger' : '');
    }

    public openModalConfirmar(template: TemplateRef<any>) {
        this.actualizarSeleccionados();
        if (this.seleccionados.length === 0) {
            this.alertService.warning('Debe seleccionar al menos un producto para realizar el ajuste masivo.','Sin productos seleccionados');
            return;
        }

        const conCambios = this.seleccionados.some(p => p.diferencia !== 0);
        if (!conCambios) {
            this.alertService.warning('Ninguno de los productos seleccionados tiene cambios de stock.','Sin cambios');
            return;
        }


        this.ajusteMasivo.productos = this.seleccionados.filter(p => p.diferencia !== 0).map(p => ({
            id_producto: p.id,
            id_bodega: p.inventario_actual?.id_bodega,
            stock_actual: p.stock_actual,
            stock_nuevo: p.stock_nuevo,
            diferencia: p.diferencia
        }));


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

        this.apiService.store('productos/ajuste-masivo', datos).subscribe(
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
        // Inicializar bodega seleccionada
        this.bodegaSeleccionada = this.bodegas.length > 0 ? this.bodegas[0] : null;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public importarAjustes(fileInput: HTMLInputElement, detalle: string) {
        if (!detalle) {
            this.alertService.warning('Debe proporcionar una descripción para el ajuste.','Descripción requerida');
            return;
        }

        if (!this.bodegaSeleccionada) {
            this.alertService.warning('Debe seleccionar una bodega para el ajuste.','Bodega requerida');
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
        formData.append('id_bodega', this.bodegaSeleccionada.id);
        formData.append('id_usuario', this.apiService.auth_user().id);
        formData.append('id_empresa', this.apiService.auth_user().id_empresa);
    
        this.saving = true;
        this.apiService.upload('productos/ajuste-masivo/importar', formData).subscribe(
            respuesta => {
                const stats = (respuesta as any).estadisticas;
                let mensaje = `<div><strong>Importación completada:</strong></div>`;
                mensaje += `<ul style="margin: 10px 0; padding-left: 20px;">`;
                mensaje += `<li>Productos procesados: <strong>${stats.procesados}</strong></li>`;
                mensaje += `<li>Productos actualizados: <strong>${stats.actualizados}</strong></li>`;
                if (stats.sin_cambios > 0) {
                    mensaje += `<li>Productos sin cambios: <strong>${stats.sin_cambios}</strong></li>`;
                }
                if (stats.sin_inventario > 0) {
                    mensaje += `<li style="color: orange;">Productos sin inventario en la bodega: <strong>${stats.sin_inventario}</strong></li>`;
                }
                if (stats.errores > 0) {
                    mensaje += `<li style="color: red;">Errores encontrados: <strong>${stats.errores}</strong></li>`;
                }
                mensaje += `</ul>`;
                
                if (stats.sin_inventario > 0 || stats.errores > 0) {
                    mensaje += `<div style="margin-top: 10px; padding: 8px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">`;
                    mensaje += `<small><i class="fa fa-info-circle"></i> Revisa los logs para más detalles sobre los productos no procesados.</small>`;
                    mensaje += `</div>`;
                }

                this.alertService.success('Importación de ajuste masivo', mensaje);
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

    // setPagination() ahora se hereda de BasePaginatedComponent

    private procesarProductosRecibidos() {
        this.seleccionados = [];
        this.ajusteMasivo.productos = [];
        
        this.productosMap.clear();
        
        if (this.productos?.data) {
            this.productos.data.forEach((producto: any) => {
                this.productosMap.set(producto.id, producto);
                
                producto.seleccionado = false;
                producto.stock_nuevo = null;
                producto.diferencia = 0;
                
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
    }
}