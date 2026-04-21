import { Component, OnInit, TemplateRef } from '@angular/core';
import { FormControl } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { debounceTime, switchMap, filter, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-traslado-masivo',
  templateUrl: './traslado-masivo.component.html',
})
export class TrasladoMasivoComponent implements OnInit {
    public productosBusqueda: any[] = [];
    public loadingBusqueda = false;
    public downloading = false;
    public saving = false;
    public savingImportDirecto = false;
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
    public bodegasMap: Map<string, string> = new Map();

    searchControl = new FormControl<string | null>('');

    modalRef!: BsModalRef;

    public importModalCargando = false;
    /** El input file no participa bien en ngForm: se usa para habilitar los botones del modal. */
    public importModalArchivoSeleccionado = false;
    private rowUidSeq = 0;
    private tieneShopify = false;

    constructor(
        public apiService: ApiService,
        private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!(empresa?.shopify_store_url && empresa?.shopify_consumer_secret && empresa?.shopify_status === 'connected');

        this.loadData();

        this.searchControl.valueChanges
            .pipe(
                debounceTime(500),
                filter((q): q is string => typeof q === 'string' && q.trim().length > 0),
                switchMap((query: string) => {
                    this.loadingBusqueda = true;
                    return this.apiService.getAll('productos/buscar-by-query', { query: query.trim() }).pipe(
                        catchError(err => {
                            console.error('Error en la búsqueda:', err);
                            this.loadingBusqueda = false;
                            return of([]);
                        })
                    );
                })
            )
            .subscribe({
                next: (results: any[]) => {
                    this.productosBusqueda = Array.isArray(results) ? results : [];
                    this.procesarProductosEncontrados();
                    this.loadingBusqueda = false;
                },
                error: () => {
                    this.loadingBusqueda = false;
                }
            });
    }

    private procesarProductosEncontrados() {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            return;
        }
        this.productosBusqueda = this.productosBusqueda.map((producto: any) => {
            const invOrigen = (producto.inventarios || []).find(
                (inv: any) => String(inv.id_bodega) === String(this.filtros.id_bodega_origen)
            );
            const invDestino = (producto.inventarios || []).find(
                (inv: any) => String(inv.id_bodega) === String(this.filtros.id_bodega_destino)
            );
            return {
                ...producto,
                stock_origen: invOrigen ? invOrigen.stock : 0,
                stock_destino: invDestino ? invDestino.stock : 0,
            };
        });
    }

    public getStockOrigen(producto: any): number {
        if (producto.stock_origen !== undefined && producto.stock_origen !== null) {
            return Number(producto.stock_origen) || 0;
        }
        const inv = (producto.inventarios || []).find(
            (i: any) => String(i.id_bodega) === String(this.filtros.id_bodega_origen)
        );
        return inv ? Number(inv.stock) || 0 : 0;
    }

    public getStockDestino(producto: any): number {
        if (producto.stock_destino !== undefined && producto.stock_destino !== null) {
            return Number(producto.stock_destino) || 0;
        }
        const inv = (producto.inventarios || []).find(
            (i: any) => String(i.id_bodega) === String(this.filtros.id_bodega_destino)
        );
        return inv ? Number(inv.stock) || 0 : 0;
    }

    public getNombreCompleto(producto: any): string {
        if (this.tieneShopify && producto.nombre_variante) {
            return `${producto.nombre} (${producto.nombre_variante})`;
        }
        return producto.nombre;
    }

    public conceptoTrasladoValido(): boolean {
        const d = this.trasladoInventario?.detalle;
        return typeof d === 'string' && d.trim().length > 0;
    }

    public loadData() {
        const filtrosGuardados = localStorage.getItem('trasladoInventarioFiltros');
        if (filtrosGuardados) {
            this.filtros = { ...this.filtros, ...JSON.parse(filtrosGuardados) };
        }

        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => { this.alertService.error(error); });

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
            this.bodegasMap.clear();
            this.bodegas.forEach((bodega: any) => {
                this.bodegasMap.set(bodega.id.toString(), bodega.nombre);
            });
            this.validarFiltrosBodegaContraLista();
        }, error => { this.alertService.error(error); });

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

    /** Limpia IDs de bodega inválidos; no fuerza valores por defecto (el usuario elige en pantalla). */
    private validarFiltrosBodegaContraLista() {
        if (!this.bodegas?.length) {
            return;
        }
        const ids = new Set(this.bodegas.map((b: any) => String(b.id)));
        const fix = (v: any) => (v !== '' && v != null && ids.has(String(v)) ? v : '');
        let o = fix(this.filtros.id_bodega_origen);
        let d = fix(this.filtros.id_bodega_destino);
        if (o && d && String(o) === String(d)) {
            d = '';
        }
        this.filtros.id_bodega_origen = o;
        this.filtros.id_bodega_destino = d;
        this.guardarFiltros();
    }

    public cambiarBodegaOrigen() {
        if (this.seleccionados.length > 0) {
            if (confirm('Al cambiar la bodega de origen se perderá la lista actual de productos a trasladar. ¿Desea continuar?')) {
                this.seleccionados = [];
                this.productosParaTraslado = [];
                this.limpiarBusquedaUi();
                this.guardarFiltros();
            } else {
                const filtrosGuardados = localStorage.getItem('trasladoInventarioFiltros');
                if (filtrosGuardados) {
                    const prev = JSON.parse(filtrosGuardados);
                    this.filtros.id_bodega_origen = prev.id_bodega_origen;
                }
            }
        } else {
            this.limpiarBusquedaUi();
            this.guardarFiltros();
        }
    }

    public cambiarBodegaDestino() {
        if (this.filtros.id_bodega_origen === this.filtros.id_bodega_destino && this.filtros.id_bodega_destino !== '') {
            this.alertService.warning('Bodega duplicada', 'No puede seleccionar la misma bodega como origen y destino.');
            this.filtros.id_bodega_destino = '';
            return;
        }
        this.procesarProductosEncontrados();
        this.guardarFiltros();
    }

    private limpiarBusquedaUi() {
        this.productosBusqueda = [];
        this.searchControl.setValue('', { emitEvent: false });
    }

    private guardarFiltros() {
        localStorage.setItem('trasladoInventarioFiltros', JSON.stringify(this.filtros));
    }

    public selectProductoDesdeBusqueda(producto: any) {
        if (this.productoYaSeleccionado(producto.id)) {
            this.alertService.info('Producto duplicado', 'Este producto ya está en el listado.');
            this.limpiarBusquedaUi();
            return;
        }

        const stockOrigen = this.getStockOrigen(producto);
        if (stockOrigen <= 0) {
            this.alertService.warning('Sin stock', 'Este producto no tiene stock disponible en la bodega de origen.');
            this.limpiarBusquedaUi();
            return;
        }

        const invOrigen = (producto.inventarios || []).find(
            (inv: any) => String(inv.id_bodega) === String(this.filtros.id_bodega_origen)
        );
        const invDestino = (producto.inventarios || []).find(
            (inv: any) => String(inv.id_bodega) === String(this.filtros.id_bodega_destino)
        );

        const fila: any = {
            uid: `m-${++this.rowUidSeq}`,
            id: producto.id,
            codigo: producto.codigo,
            nombre: this.getNombreCompleto(producto),
            nombre_categoria: producto.nombre_categoria,
            img: producto.img || 'default.png',
            stock_origen: stockOrigen,
            stock_destino: this.getStockDestino(producto),
            id_bodega_origen: this.filtros.id_bodega_origen,
            id_bodega_destino: this.filtros.id_bodega_destino,
            id_inventario_origen: invOrigen ? invOrigen.id : null,
            id_inventario_destino: invDestino ? invDestino.id : null,
            cantidad_traslado: 1,
        };

        this.aplicarReglasCantidadTraslado(fila, {});
        this.seleccionados = [...this.seleccionados, fila];
        this.actualizarProductosParaTraslado();
        this.limpiarBusquedaUi();
    }

    private productoYaSeleccionado(idProducto: number): boolean {
        return this.seleccionados.some(p => p.id === idProducto);
    }

    public quitarProducto(producto: any) {
        this.seleccionados = this.seleccionados.filter(p => p.uid !== producto.uid);
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

    public listadoTieneFilasDeImportacion(): boolean {
        return this.seleccionados.some(p => p.importacion_preview_ok !== undefined);
    }

    public puedeEditarCantidadTraslado(p: any): boolean {
        return p.importacion_preview_ok !== false && this.stockOrigenNumerico(p) > 0;
    }

    private stockOrigenNumerico(p: any): number {
        return Math.max(0, Math.floor(Number(p?.stock_origen)) || 0);
    }

    public cantidadTrasladoInputInvalida(p: any): boolean {
        if (p.importacion_preview_ok === false) {
            return false;
        }
        const stock = this.stockOrigenNumerico(p);
        if (stock <= 0) {
            return false;
        }
        const q = Number(p.cantidad_traslado);
        const bad = !Number.isFinite(q) || !Number.isInteger(q) || q < 1 || q > stock;
        if (p.importacion_preview_ok === true) {
            return bad;
        }
        return bad;
    }

    public hayFilasConCantidadOStockInvalidosParaTraslado(): boolean {
        return this.seleccionados.some(p => this.cantidadTrasladoInputInvalida(p));
    }

    public hayFilasConErrorImportacion(): boolean {
        return this.seleccionados.some(p => p.importacion_preview_ok === false);
    }

    public validarCantidadTraslado(producto: any) {
        this.aplicarReglasCantidadTraslado(producto, { avisoSuperaStock: true });
        this.actualizarProductosParaTraslado();
    }

    private aplicarReglasCantidadTraslado(producto: any, opts: { avisoSuperaStock?: boolean } = {}) {
        if (producto.importacion_preview_ok === false) {
            return;
        }
        const stock = this.stockOrigenNumerico(producto);
        let q = Number(producto.cantidad_traslado);
        if (!Number.isFinite(q)) {
            q = stock > 0 ? 1 : 0;
        }
        q = Math.floor(q);
        if (q < 0) {
            q = 0;
        }
        if (stock <= 0) {
            producto.cantidad_traslado = 0;
            return;
        }
        if (q < 1) {
            producto.cantidad_traslado = 1;
            return;
        }
        if (q > stock) {
            producto.cantidad_traslado = stock;
            if (opts.avisoSuperaStock) {
                this.alertService.warning(
                    'Cantidad inválida',
                    'No puede trasladar más unidades que el stock disponible en la bodega origen.'
                );
            }
            return;
        }
        producto.cantidad_traslado = q;
    }

    private sanearCantidadesEnListado() {
        for (const p of this.seleccionados) {
            this.aplicarReglasCantidadTraslado(p, {});
        }
        this.actualizarProductosParaTraslado();
    }

    public actualizarProductosParaTraslado() {
        const cantidadOk = (p: any) => {
            const q = Number(p.cantidad_traslado);
            const stock = this.stockOrigenNumerico(p);
            return Number.isFinite(q) && Number.isInteger(q) && q >= 1 && stock > 0 && q <= stock;
        };
        this.productosParaTraslado = this.seleccionados
            .filter(p => p.importacion_preview_ok !== false && cantidadOk(p))
            .map(p => ({
                id_producto: p.id,
                id_inventario_origen: p.id_inventario_origen,
                id_inventario_destino: p.id_inventario_destino,
                id_bodega_origen: p.id_bodega_origen ?? this.filtros.id_bodega_origen,
                id_bodega_destino: p.id_bodega_destino ?? this.filtros.id_bodega_destino,
                stock_origen: p.stock_origen,
                stock_destino: p.stock_destino,
                cantidad_traslado: p.cantidad_traslado,
                nombre: p.nombre
            }));
    }

    public getNombreBodega(idBodega: string): string {
        return this.bodegasMap.get(String(idBodega)) || 'Bodega no encontrada';
    }

    public openModalConfirmar(template: TemplateRef<any>) {
        if (!this.conceptoTrasladoValido()) {
            this.alertService.warning('Concepto requerido', 'Debe proporcionar una descripción para el traslado.');
            return;
        }

        this.sanearCantidadesEnListado();

        if (this.hayFilasConErrorImportacion()) {
            this.alertService.warning(
                'Errores en importación',
                'Hay al menos una fila con error en el listado. Quite esas filas o corrija el Excel y vuelva a importar antes de realizar el traslado.'
            );
            return;
        }

        if (this.hayFilasConCantidadOStockInvalidosParaTraslado()) {
            this.alertService.warning(
                'Cantidades inválidas',
                'Hay productos con cantidad a trasladar inválida o sin stock en origen. Corrija las cantidades o quite filas con error.'
            );
            return;
        }

        if (this.productosParaTraslado.length === 0) {
            this.alertService.warning(
                'Sin productos para trasladar',
                'No hay líneas listas para trasladar: revise cantidades (1 hasta stock en origen), filas en error y el concepto.'
            );
            return;
        }

        this.trasladoInventario.productos = this.productosParaTraslado;
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    public realizarTraslado() {
        if (!this.conceptoTrasladoValido()) {
            this.alertService.warning('Descripción requerida', 'Debe proporcionar una descripción para el traslado.');
            return;
        }

        this.sanearCantidadesEnListado();

        if (this.hayFilasConErrorImportacion()) {
            this.alertService.warning('Errores en importación', 'Hay al menos una fila con error en el listado. Corrija antes de confirmar.');
            return;
        }

        if (this.hayFilasConCantidadOStockInvalidosParaTraslado()) {
            this.alertService.warning('Cantidades inválidas', 'Corrija el listado antes de confirmar.');
            return;
        }

        if (this.productosParaTraslado.length === 0) {
            this.alertService.warning('Sin productos para trasladar', 'No hay productos válidos para trasladar.');
            return;
        }

        this.saving = true;

        const idOrigen = String(this.productosParaTraslado[0].id_bodega_origen);
        const idDestino = String(this.productosParaTraslado[0].id_bodega_destino);
        const bodegasInconsistentes = this.productosParaTraslado.some(
            p => String(p.id_bodega_origen) !== idOrigen || String(p.id_bodega_destino) !== idDestino
        );
        if (bodegasInconsistentes) {
            this.alertService.warning(
                'Bodegas distintas',
                'El traslado masivo requiere la misma bodega de origen y la misma de destino en todas las líneas.'
            );
            this.saving = false;
            return;
        }

        const datos = {
            concepto: String(this.trasladoInventario.detalle).trim(),
            id_bodega_origen: idOrigen,
            id_bodega_destino: idDestino,
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
                this.seleccionados = [];
                this.productosParaTraslado = [];
            },
            error => {
                this.alertService.error(error);
                this.saving = false;
            }
        );
    }

    public descargarPlantillaImportacion() {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Bodegas requeridas', 'Seleccione bodega origen y destino para descargar la plantilla.');
            return;
        }

        this.downloading = true;
        const filtrosExport = {
            id_bodega_origen: this.filtros.id_bodega_origen,
            id_bodega_destino: this.filtros.id_bodega_destino,
            formato: 'excel',
        };

        this.apiService.export('productos/exportar-traslado', filtrosExport).subscribe(
            (data: Blob) => {
                this.guardarBlobExcel(data, 'plantilla_traslado_importacion.xlsx');
                this.downloading = false;
            },
            (error) => {
                this.alertService.error(error);
                this.downloading = false;
            }
        );
    }

    public exportarPlantilla() {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Bodegas requeridas', 'Seleccione bodegas origen y destino para exportar la lista.');
            return;
        }

        if (this.seleccionados.length === 0) {
            this.alertService.warning('Sin productos', 'No hay productos en la lista para exportar.');
            return;
        }

        this.downloading = true;

        const idOrigen = (p: any) => p.id_bodega_origen ?? this.filtros.id_bodega_origen;
        const idDestino = (p: any) => p.id_bodega_destino ?? this.filtros.id_bodega_destino;

        const lineas = this.seleccionados
            .filter(p => p.id != null)
            .map(p => ({
                id_producto: p.id,
                codigo: p.codigo,
                nombre: p.nombre,
                nombre_categoria: p.nombre_categoria ?? '',
                id_bodega_origen: idOrigen(p),
                id_bodega_destino: idDestino(p),
                nombre_bodega_origen: this.getNombreBodega(String(idOrigen(p))),
                nombre_bodega_destino: this.getNombreBodega(String(idDestino(p))),
                stock_origen: Number(p.stock_origen ?? 0),
                stock_destino: Number(p.stock_destino ?? 0),
                cantidad_traslado: Number(p.cantidad_traslado ?? 0),
            }));

        this.apiService.exportPost('productos/exportar-traslado', { lineas }).subscribe(
            (data: Blob) => {
                this.guardarBlobExcel(data, 'traslado_inventario.xlsx');
                this.downloading = false;
            },
            (error) => {
                this.alertService.error(error);
                this.downloading = false;
            }
        );
    }

    private guardarBlobExcel(data: Blob, nombreArchivo: string) {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nombreArchivo;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    public openModalImportar(template: TemplateRef<any>) {
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Bodegas requeridas', 'Seleccione las bodegas origen y destino primero.');
            return;
        }
        this.importModalArchivoSeleccionado = false;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
        setTimeout(() => {
            const el = document.getElementById('archivo-traslado-masivo-import') as HTMLInputElement | null;
            if (el) {
                el.value = '';
            }
        }, 0);
    }

    public onImportModalArchivoChange(event: Event) {
        const input = event.target as HTMLInputElement;
        this.importModalArchivoSeleccionado = !!(input.files && input.files.length > 0);
    }

    /** Concepto no vacío + archivo elegido (el file no valida bien en ngForm). */
    public importModalListoParaAccion(): boolean {
        return this.conceptoTrasladoValido() && this.importModalArchivoSeleccionado;
    }

    public cargarImportacionAlListado(fileInput: HTMLInputElement, detalle: string) {
        const concepto = (detalle ?? '').trim();
        if (!concepto) {
            this.alertService.warning('Descripción requerida', 'Escriba el concepto del traslado para la vista previa.');
            return;
        }

        if (!fileInput.files || fileInput.files.length === 0) {
            this.alertService.warning('Archivo requerido', 'Debe seleccionar un archivo.');
            return;
        }

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('archivo', file);
        formData.append('concepto', concepto);
        formData.append('id_bodega_origen', this.filtros.id_bodega_origen);
        formData.append('id_bodega_destino', this.filtros.id_bodega_destino);
        formData.append('id_usuario', this.traslado.id_usuario || this.apiService.auth_user().id);

        this.importModalCargando = true;
        this.apiService.upload('productos/traslado-masivo/importar/vista-previa', formData).subscribe(
            (respuesta: any) => {
                this.importModalCargando = false;
                const filas = respuesta.filas || [];
                const filasOk = respuesta.filas_ok ?? 0;
                const total = respuesta.total_filas ?? filas.length;

                this.aplicarFilasImportacionAListado(filas);
                this.trasladoInventario.detalle = concepto;

                this.modalRef.hide();

                if (filasOk === 0 && total > 0) {
                    this.alertService.warning(
                        'Sin líneas válidas',
                        'Ninguna línea del archivo está lista para trasladar. Revise la tabla y corrija el Excel.'
                    );
                } else if (total === 0) {
                    this.alertService.info('Importación', 'No se encontraron filas con datos en el archivo.');
                } else if (filasOk < total) {
                    this.alertService.info(
                        'Vista previa en listado',
                        `Se cargaron ${total} fila(s): ${filasOk} listas y ${total - filasOk} con error. Revise la tabla antes de realizar el traslado.`
                    );
                }
            },
            (error) => {
                this.alertService.error(error);
                this.importModalCargando = false;
            }
        );
    }

    /**
     * Flujo clásico: importa el Excel y ejecuta los traslados en el servidor sin vista previa en tabla.
     */
    public importarTrasladoDirecto(fileInput: HTMLInputElement, detalle: string) {
        const concepto = (detalle ?? '').trim();
        if (!concepto) {
            this.alertService.warning('Descripción requerida', 'Escriba el concepto del traslado.');
            return;
        }

        if (!fileInput.files || fileInput.files.length === 0) {
            this.alertService.warning('Archivo requerido', 'Debe seleccionar un archivo.');
            return;
        }

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('archivo', file);
        formData.append('concepto', concepto);
        formData.append('id_bodega_origen', this.filtros.id_bodega_origen);
        formData.append('id_bodega_destino', this.filtros.id_bodega_destino);
        formData.append('id_usuario', String(this.traslado.id_usuario || this.apiService.auth_user().id));

        this.savingImportDirecto = true;
        this.apiService.upload('productos/traslado-masivo/importar', formData).subscribe(
            (respuesta: any) => {
                this.savingImportDirecto = false;
                const n = respuesta.trasladados ?? 0;
                this.alertService.success('Importación completada', respuesta.message || `Se trasladaron ${n} producto(s).`);
                this.modalRef.hide();
                this.seleccionados = [];
                this.productosParaTraslado = [];
                this.limpiarBusquedaUi();

                if (respuesta.errores && respuesta.errores.length > 0) {
                    const msg = 'Algunas líneas no se procesaron: ' + respuesta.errores.slice(0, 5).join('; ')
                        + (respuesta.errores.length > 5 ? '…' : '');
                    this.alertService.warning('Advertencias', msg);
                }
            },
            error => {
                this.alertService.error(error);
                this.savingImportDirecto = false;
            }
        );
    }

    private aplicarFilasImportacionAListado(filas: any[]) {
        this.seleccionados = filas.map((f: any) => ({
            uid: `i-${f.fila}-${++this.rowUidSeq}`,
            id: f.id_producto,
            nombre: f.producto || '—',
            nombre_categoria: f.nombre_categoria || '—',
            img: f.img || 'default.png',
            stock_origen: f.stock_origen ?? 0,
            stock_destino: f.stock_destino ?? 0,
            id_bodega_origen: f.id_bodega_origen ?? null,
            id_bodega_destino: f.id_bodega_destino ?? null,
            id_inventario_origen: f.id_inventario_origen ?? null,
            id_inventario_destino: f.id_inventario_destino ?? null,
            cantidad_traslado: f.ok ? (f.cantidad ?? 0) : 0,
            importacion_preview_ok: f.ok,
            importacion_preview_error: f.error || null,
            fila_excel: f.fila,
        }));
        this.sanearCantidadesEnListado();
    }
}
