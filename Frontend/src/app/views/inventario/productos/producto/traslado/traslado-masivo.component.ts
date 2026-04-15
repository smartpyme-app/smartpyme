import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-traslado-masivo',
  templateUrl: './traslado-masivo.component.html',
})
export class TrasladoMasivoComponent implements OnInit {
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
    public bodegasMap: Map<string, string> = new Map();

    modalRef!: BsModalRef;

    public importModalCargando = false;
    private rowUidSeq = 0;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.loadData();
    }

    /** Debe escribirse primero en pantalla para habilitar plantilla e importación (no solo espacios). */
    public conceptoTrasladoValido(): boolean {
        const d = this.trasladoInventario?.detalle;
        return typeof d === 'string' && d.trim().length > 0;
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

            this.asegurarFiltrosBodega();
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

    /** Sin selectores de bodega en UI: valida filtros guardados o asigna dos bodegas distintas por defecto. */
    private asegurarFiltrosBodega() {
        if (!this.bodegas?.length) {
            return;
        }
        const ids = this.bodegas.map((b: any) => String(b.id));
        const origenOk = this.filtros.id_bodega_origen && ids.includes(String(this.filtros.id_bodega_origen));
        if (!origenOk) {
            this.filtros.id_bodega_origen = ids[0];
        }
        const destinoOk = this.filtros.id_bodega_destino && ids.includes(String(this.filtros.id_bodega_destino));
        const mismoQueOrigen = String(this.filtros.id_bodega_destino) === String(this.filtros.id_bodega_origen);
        if (!destinoOk || mismoQueOrigen) {
            const otro = ids.find((id: string) => id !== String(this.filtros.id_bodega_origen));
            this.filtros.id_bodega_destino = otro ?? ids[0];
        }
        this.guardarFiltros();
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

    /** Fila en la que el usuario puede editar cantidad (no error de importación y con stock en origen). */
    public puedeEditarCantidadTraslado(p: any): boolean {
        return p.importacion_preview_ok !== false && this.stockOrigenNumerico(p) > 0;
    }

    private stockOrigenNumerico(p: any): number {
        return Math.max(0, Math.floor(Number(p?.stock_origen)) || 0);
    }

    /**
     * Cantidad inválida en filas marcadas como «Listo» por la importación (entero 1..stock; stock en origen > 0).
     * Las filas en error no bloquean ni muestran este estado en cantidad.
     */
    public cantidadTrasladoInputInvalida(p: any): boolean {
        if (p.importacion_preview_ok !== true) {
            return false;
        }
        const stock = this.stockOrigenNumerico(p);
        if (stock <= 0) {
            return true;
        }
        const q = Number(p.cantidad_traslado);
        if (!Number.isFinite(q) || !Number.isInteger(q)) {
            return true;
        }
        return q < 1 || q > stock;
    }

    /** Bloquea «Realizar traslado» si alguna fila «Listo» tiene cantidad o stock inconsistente. */
    public hayFilasConCantidadOStockInvalidosParaTraslado(): boolean {
        return this.seleccionados.some(p => this.cantidadTrasladoInputInvalida(p));
    }

    public validarCantidadTraslado(producto: any) {
        this.aplicarReglasCantidadTraslado(producto, { avisoSuperaStock: true });
        this.actualizarProductosParaTraslado();
    }

    /**
     * Normaliza cantidad: entero, mínimo 1 si hay stock, máximo stock, 0 si no hay stock o fila en error.
     */
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
                    'No puede trasladar más unidades que el stock disponible en la bodega origen.',
                    'Cantidad inválida'
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
        this.productosParaTraslado = this.seleccionados.filter(
            p => p.importacion_preview_ok !== false
                && cantidadOk(p)
        ).map(p => ({
            id_producto: p.id,
            id_inventario_origen: p.id_inventario_origen,
            id_inventario_destino: p.id_inventario_destino,
            id_bodega_origen: p.id_bodega_origen ?? this.filtros.id_bodega_origen,
            id_bodega_destino: p.id_bodega_destino ?? this.filtros.id_bodega_destino,
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
        if (!this.conceptoTrasladoValido()) {
            this.alertService.warning('Debe proporcionar una descripción para el traslado.', 'Concepto requerido');
            return;
        }

        this.sanearCantidadesEnListado();

        if (this.hayFilasConCantidadOStockInvalidosParaTraslado()) {
            this.alertService.warning(
                'Hay productos con cantidad a trasladar inválida o sin stock en origen. Corrija las cantidades (enteras entre 1 y el stock), quite filas con error o ajuste el Excel y vuelva a importar.',
                'Cantidades inválidas'
            );
            return;
        }

        if (this.productosParaTraslado.length === 0) {
            this.alertService.warning(
                'No hay líneas listas para trasladar: revise cantidades (1 hasta stock en origen), filas en error y el concepto.',
                'Sin productos para trasladar'
            );
            return;
        }

        this.trasladoInventario.productos = this.productosParaTraslado;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public realizarTraslado() {
        if (!this.conceptoTrasladoValido()) {
            this.alertService.warning('Debe proporcionar una descripción para el traslado.', 'Descripción requerida');
            return;
        }

        this.sanearCantidadesEnListado();

        if (this.hayFilasConCantidadOStockInvalidosParaTraslado()) {
            this.alertService.warning(
                'Hay cantidades inválidas o sin stock en origen. Corrija el listado antes de confirmar.',
                'Cantidades inválidas'
            );
            return;
        }

        if (this.productosParaTraslado.length === 0) {
            this.alertService.warning(
                'No hay productos válidos para trasladar. Revise cantidades y filas en error.',
                'Sin productos para trasladar'
            );
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
                'El traslado masivo requiere la misma bodega de origen y la misma de destino en todas las líneas. Corrija el Excel o el listado.',
                'Bodegas distintas'
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
                
                // Limpiar productos seleccionados
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
        if (!this.conceptoTrasladoValido()) {
            this.alertService.warning('Escriba primero el concepto del traslado.', 'Concepto requerido');
            return;
        }
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Debe seleccionar las bodegas origen y destino para descargar la plantilla.', 'Bodegas requeridas');
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
        if (!this.conceptoTrasladoValido()) {
            this.alertService.warning('Escriba primero el concepto del traslado.', 'Concepto requerido');
            return;
        }
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Debe seleccionar las bodegas origen y destino para exportar la lista.', 'Bodegas requeridas');
            return;
        }
        
        if (this.seleccionados.length === 0) {
            this.alertService.warning('No hay productos en la lista para exportar.', 'Sin productos');
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
        if (!this.conceptoTrasladoValido()) {
            this.alertService.warning('Escriba primero el concepto del traslado.', 'Concepto requerido');
            return;
        }
        if (!this.filtros.id_bodega_origen || !this.filtros.id_bodega_destino) {
            this.alertService.warning('Debe seleccionar las bodegas origen y destino primero.', 'Bodegas requeridas');
            return;
        }

        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    /**
     * Lee el Excel (vista previa en servidor) y llena la tabla principal. El traslado se ejecuta con «Realizar traslado» como el flujo manual.
     */
    public cargarImportacionAlListado(fileInput: HTMLInputElement, detalle: string) {
        const concepto = (detalle ?? '').trim();
        if (!concepto) {
            this.alertService.warning('Debe proporcionar una descripción para el traslado.', 'Descripción requerida');
            return;
        }

        if (!fileInput.files || fileInput.files.length === 0) {
            this.alertService.warning('Debe seleccionar un archivo.', 'Archivo requerido');
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
                        'Ninguna línea del archivo está lista para trasladar. Revise la tabla y corrija el Excel.',
                        'Sin líneas válidas'
                    );
                } else if (total === 0) {
                    this.alertService.info('No se encontraron filas con datos en el archivo.', 'Importación');
                } else if (filasOk < total) {
                    this.alertService.info(
                        `Se cargaron ${total} fila(s): ${filasOk} listas y ${total - filasOk} con error. Revise la tabla antes de realizar el traslado.`,
                        'Vista previa en listado'
                    );
                }
            },
            (error) => {
                this.alertService.error(error);
                this.importModalCargando = false;
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