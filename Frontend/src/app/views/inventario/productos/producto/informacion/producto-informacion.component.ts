import { Component, OnInit, OnChanges, SimpleChanges, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import Swal from 'sweetalert2';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-informacion',
  templateUrl: './producto-informacion.component.html'
})
export class ProductoInformacionComponent implements OnInit, OnChanges {

    @Input() producto: any = {};
    @Output() productoGuardado = new EventEmitter<any>();
    /** Evita múltiples llamadas al pedir código de barras sugerido */
    private barcodeCorrelativoPendiente = true;
    public categorias:any = [];
    public usuario:any = {};
    public categoria:any = {};
    public bodegas:any = [];
    public medidas:any = [];
    public impuestos: any[] = [];
    public loading = false;
    public guardar = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        
        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('impuestos').subscribe(impuestos => {
            this.impuestos = (impuestos || []).filter((i: any) => i.aplica_ventas);
            this.inicializarImpuestosProducto();
        }, () => { this.impuestos = []; });

        this.medidas = JSON.parse(localStorage.getItem('unidades_medidas')!);

        this.intentarCargarBarcodeSugerido();
    }

    ngOnChanges(changes: SimpleChanges) {
        if (changes['producto']) {
            this.inicializarImpuestosProducto();
            this.intentarCargarBarcodeSugerido();
        }
    }

    /** Sincroniza id_impuestos desde la relación o desde porcentaje_impuesto legacy. */
    private inicializarImpuestosProducto(): void {
        const p = this.producto;
        if (!p) return;

        if (Array.isArray(p.impuestos) && p.impuestos.length > 0) {
            p.id_impuestos = p.impuestos.map((i: any) => i.id);
            return;
        }

        if (Array.isArray(p.id_impuestos) && p.id_impuestos.length > 0) {
            return;
        }

        const pct = p.porcentaje_impuesto;
        if (pct != null && pct !== '' && Number(pct) > 0 && this.impuestos.length > 0) {
            const match = this.impuestos.find((i: any) => Number(i.porcentaje) === Number(pct));
            p.id_impuestos = match ? [match.id] : [];
            return;
        }

        if (pct === 0 || pct === '0') {
            p.id_impuestos = [];
            return;
        }

        const ivaEmpresa = Number(this.usuario?.empresa?.iva ?? 0);
        if (ivaEmpresa > 0 && this.impuestos.length > 0 && (pct == null || pct === '')) {
            const match = this.impuestos.find((i: any) => Number(i.porcentaje) === ivaEmpresa);
            if (match) {
                p.id_impuestos = [match.id];
            }
        }
    }

    public onImpuestosChange(): void {
        this.calPrecioFinal();
    }

    public getImpuestosSeleccionados(): any[] {
        const ids: number[] = Array.isArray(this.producto?.id_impuestos) ? this.producto.id_impuestos : [];
        return this.impuestos.filter((i: any) => ids.includes(i.id));
    }

    /** Precarga el código de barras correlativo desde la API cuando aplica (producto nuevo + opción de empresa). */
    private intentarCargarBarcodeSugerido() {
        const p = this.producto;
        if (!p || p.id || !this.apiService.isBarcodeCorrelativoAutomatico()) {
            return;
        }
        if (p.barcode) {
            this.barcodeCorrelativoPendiente = false;
            return;
        }
        if (!p.id_empresa) {
            return;
        }
        if (!this.barcodeCorrelativoPendiente) {
            return;
        }

        this.barcodeCorrelativoPendiente = false;
        this.apiService.getAll('productos/siguiente-barcode-correlativo').subscribe(
            (res: any) => {
                const valor = res?.barcode ?? res?.codigo;
                if (res?.habilitado && valor != null && p === this.producto && !this.producto.barcode) {
                    this.producto.barcode = String(valor);
                }
            },
            () => {
                this.barcodeCorrelativoPendiente = true;
            }
        );
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

    public actualizarCostoPromedio(){
        this.producto.costo_promedio = this.producto.costo;
    }

    public actualizarCosto(){
        this.producto.costo = this.producto.costo_promedio;
    }

    /** Suma de tasas de los impuestos seleccionados (cálculo paralelo sobre la base). */
    public getPorcentajeProducto(): number {
        const seleccionados = this.getImpuestosSeleccionados();
        if (seleccionados.length > 0) {
            return seleccionados.reduce((sum: number, i: any) => sum + Number(i.porcentaje || 0), 0);
        }
        const p = this.producto?.porcentaje_impuesto;
        if (p != null && p !== '') return Number(p);
        return Number(this.usuario?.empresa?.iva ?? 0);
    }

    public calPrecioBase(){
        const pct = this.getPorcentajeProducto();
        if (pct <= 0) return;
        this.producto.impuesto = pct / 100;
        this.producto.precio = (this.producto.precio_final / (1 + (this.producto.impuesto * 1))).toFixed(4);
    }

    public calPrecioFinal(){
        const pct = this.getPorcentajeProducto();
        if (pct <= 0) return;
        this.producto.impuesto = pct / 100;
        this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
    }


    public onInventarioPorLotesChange() {
        if (!this.producto.id) {
            return;
        }

        if (!this.producto.inventario_por_lotes) {
            this.onSubmit();
            return;
        }

        this.apiService.getAll(`producto/${this.producto.id}/preview-migracion-lotes`).subscribe(
            (preview: any) => {
                if (preview.requiere_migracion) {
                    const listaHtml = (preview.bodegas || [])
                        .map((b: any) => `<li>${b.nombre_bodega}: <strong>${b.stock_inventario}</strong> uds</li>`)
                        .join('');
                    Swal.fire({
                        title: 'Inventario por lotes',
                        html: `
                            <p class="text-start mb-2">
                                A partir de ahora este producto manejará su stock <strong>por lotes</strong>.
                                Para no perder las existencias actuales, el sistema trasladará automáticamente
                                ese stock a un <strong>lote inicial</strong> (<em>${preview.numero_lote || 'STOCK-INICIAL'}</em>) en cada bodega.
                            </p>
                            <p class="text-start mb-2">
                                Después podrá editar ese lote y asignarle el <strong>código de lote</strong>
                                y la <strong>fecha de vencimiento</strong> que correspondan.
                            </p>
                            <p class="text-start mb-1"><strong>Stock a trasladar</strong> (${preview.total_bodegas} bodega(s), ${preview.total_unidades} uds en total):</p>
                            <ul class="text-start mb-0">${listaHtml}</ul>
                        `,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Activar lotes',
                        cancelButtonText: 'Cancelar',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.onSubmit();
                        } else {
                            this.producto.inventario_por_lotes = false;
                        }
                    });
                    return;
                }
                this.onSubmit();
            },
            (error) => {
                this.producto.inventario_por_lotes = false;
                this.alertService.error(error);
            }
        );
    }

    public onSubmit() {
        this.guardar = true;
        if(!this.producto.id){
            if(!this.producto.costo){
                this.producto.costo = this.producto.costo_promedio;
            }
            if(!this.producto.costo_promedio){
                this.producto.costo_promedio = this.producto.costo;
            }
        }

        const esNuevo = !this.producto.id;

        const payload = {
            ...this.producto,
            id_impuestos: Array.isArray(this.producto.id_impuestos) ? this.producto.id_impuestos : [],
        };

        this.apiService.store('producto', payload).subscribe(producto => {
            this.guardar = false;
            if (esNuevo) {
                this.producto = producto;
            } else {
                Object.assign(this.producto, producto);
            }
            this.productoGuardado.emit(producto);

            if (producto.migracion_lotes && (producto.migracion_lotes.lotes_creados > 0 || producto.migracion_lotes.unidades_migradas > 0)) {
                const m = producto.migracion_lotes;
                this.alertService.success(
                    'Stock migrado a lotes',
                    `Se creó el lote STOCK-INICIAL con ${m.unidades_migradas} unidad(es) en ${m.lotes_creados} bodega(s).`
                );
            }
            if(this.producto.tipo == 'Producto'){
                if (esNuevo) {
                    this.router.navigate(['/producto/editar/' + producto.id]);
                }
                this.alertService.success("Producto guardado", 'El producto fue guardado exitosamente.');
            }
            if(this.producto.tipo == 'Servicio'){
                if (esNuevo) {
                    this.router.navigate(['/servicio/editar/' + producto.id]);
                }
                this.alertService.success("Servicio guardado", 'El servicio fue guardado exitosamente.');
            }
            if(this.producto.tipo == 'Compuesto'){
                if (esNuevo) {
                    this.router.navigate(['/producto/editar/' + producto.id]);
                }
                this.alertService.success("Producto compuesto guardado", 'El producto compuesto fue guardado exitosamente.');
            }
            if(this.producto.tipo == 'Materia Prima'){
                if (esNuevo) {
                    this.router.navigate(['/materias-prima/editar/' + producto.id]);
                }
                this.alertService.success("Materia prima guardada", 'La materia prima fue guardada exitosamente.');
            }
        },error => {this.alertService.error(error); this.guardar = false; });
    }

    public barcode() {
        const raw = String(this.producto.barcode || this.producto.codigo || '').trim();
        if (!raw) {
            return;
        }
        window.open(
            this.apiService.baseUrl + '/api/barcode/' + encodeURIComponent(raw) + '?token=' + this.apiService.auth_token(),
            '_new',
            'toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900'
        );
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

    public isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

    public isComponenteQuimicoHabilitado(): boolean {
        return this.apiService.isComponenteQuimicoHabilitado();
    }

}
