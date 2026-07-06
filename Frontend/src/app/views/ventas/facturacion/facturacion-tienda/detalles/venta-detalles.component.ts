import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import {
    calcularMontosLineaDetalle,
    limpiarExentaPorSinIvaSiTipoManual,
    sincronizarTipoGravadoPorCobroIva,
} from '@utils/impuestos-venta.util';
import {
    autoDistribuirCantidadesLotes,
    asignacionLotesExcedeStock,
    factorConversionDetalle,
    limpiarAsignacionLotesDetalle,
    stockBaseAUnidadesDetalle,
    textoResumenLotesDetalle,
    totalAsignadoUnidadesLotes,
    formatCantidadLote,
} from '@utils/lotes-venta.util';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-venta-detalles',
  templateUrl: './venta-detalles.component.html'
})
export class VentaDetallesComponent implements OnInit {

    @Input() venta: any = {};
    @Input() usuarios: any = {};
    /** Desde facturación: al activar, muestra en cada línea el input Cta. terceros junto a Precio. */
    @Input() habilitarCuentaTerceros = false;
    public usuario:any = {};
    public detalle:any = {};
    public composicion:any = {};
    public supervisor:any = {};

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    @Output() alMenosUnPaqueteConCuentaTerceros = new EventEmitter<void>();
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;
    
    @ViewChild('mloteVenta')
    public mloteVenta!: TemplateRef<any>;

    public buscador:string = '';
    public loading:boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
    }

    get mostrarCuentaTercerosEnLinea(): boolean {
        return this.habilitarCuentaTerceros
            && this.venta?.cotizacion != 1
            && this.usuario?.tipo !== 'Ventas Limitado';
    }

    /** Mientras el módulo de paquetes esté activo, el monto en línea no se edita en el front (viene del paquete / API). */
    get cuentaTercerosLineaSoloLectura(): boolean {
        return !!this.usuario?.empresa?.modulo_paquetes;
    }

    /** Número de columnas al no hay detalles (incl. columna Cta. terceros si aplica). */
    get colspanFilaVaciaDetalles(): number {
        let n = 6;
        if (this.usuario?.empresa?.vendedor_detalle_venta) { n += 1; }
        if (this.usuario?.empresa?.cambiar_tipo_impuesto_venta) { n += 1; }
        if (this.mostrarCuentaTercerosEnLinea) { n += 1; }
        return n;
    }

    onAlMenosUnPaqueteCuentaTercerosEnListado(): void {
        this.alMenosUnPaqueteConCuentaTerceros.emit();
    }

    onCuentaTercerosLineaChange(detalle: any): void {
        if (this.cuentaTercerosLineaSoloLectura) {
            return;
        }
        const v = detalle.cuenta_a_terceros;
        if (v === '' || v == null) {
            detalle.cuenta_a_terceros = 0;
        } else {
            const n = parseFloat(String(v));
            detalle.cuenta_a_terceros = isNaN(n) ? 0 : Math.max(0, n);
        }
        this.update.emit(this.venta);
        this.sumTotal.emit();
    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    private obtenerPorcentajeIvaDetalle(detalle: any): number {
        if (!this.venta.cobrar_impuestos) {
            return 0;
        }
        const pct = (detalle?.porcentaje_impuesto != null && detalle?.porcentaje_impuesto !== '')
            ? Number(detalle.porcentaje_impuesto)
            : (this.apiService.auth_user().empresa?.iva ?? 0);
        return Number(pct) || 0;
    }

    /** Aplica gravada/exenta/no_sujeta; IVA alineado con total con IVA redondeado por línea. */
    private aplicarTipoGravado(detalle: any) {
        calcularMontosLineaDetalle(
            detalle,
            !!this.venta.cobrar_impuestos,
            this.apiService.auth_user()?.empresa?.iva
        );
    }

    public updateTotal(detalle:any){
        const cantidad = parseFloat(detalle.cantidad ?? 0) || 0;
        const precio = parseFloat(detalle.precio ?? 0) || 0;

        if(detalle.descuento_porcentaje){
            detalle.descuento = Number((cantidad * (precio * (detalle.descuento_porcentaje / 100))).toFixed(4));
        }else if(detalle.descuento_monto){
            detalle.descuento = Number((cantidad * detalle.descuento_monto).toFixed(4));
        }else{
            detalle.descuento = 0;
        }

        detalle.total_costo  = (cantidad * parseFloat(detalle.costo ?? 0)).toFixed(4);
        if (!this.skipLimpiarLotes && detalle.inventario_por_lotes && this.getLotesMetodologia() === 'Manual') {
            limpiarAsignacionLotesDetalle(detalle);
        }
        this.aplicarTipoGravado(detalle);
        this.update.emit(this.venta);
        this.sumTotal.emit();
    }

    public onTipoGravadoChange(detalle: any) {
        limpiarExentaPorSinIvaSiTipoManual(detalle);
        this.aplicarTipoGravado(detalle);
        this.update.emit(this.venta);
        this.sumTotal.emit();
    }

    public modalSupervisor(detalle:any){
        this.detalle = detalle;
        this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public openModalCompuesto(template: TemplateRef<any>, composicion:any){
        this.composicion = composicion;
        console.log(this.composicion);
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.apiService.store('usuario-validar', this.supervisor).subscribe(supervisor => {
            this.modalRef.hide();
            this.delete(this.detalle);
            this.loading = false;
            this.supervisor = {};
        },error => {this.alertService.error(error); this.loading = false; });
    }

    // Agregar detalle
        productoSelect(producto:any):void{

            if (producto.tipo === 'Servicio') {
                this.addDetalle(producto);
                return;
            }

            // Validar stock solo para productos (no servicios)
            if (producto.stock !== null && producto.stock !== undefined) {
                // Verificar si hay suficiente stock para la cantidad solicitada
                const stockDisponible = parseFloat(producto.stock) || 0;
                const cantidadRequerida = parseFloat(producto.cantidad) || 1;
                
                if (stockDisponible < cantidadRequerida) {
                    // Si la empresa no permite vender sin stock
                    if (this.apiService.auth_user().empresa.vender_sin_stock == 0) {

                      if (this.apiService.auth_user().codigo_autorizacion) {
                        
                        Swal.fire({
                              title: 'Stock insuficiente',
                              html: `El producto <strong>${producto.nombre || producto.descripcion}</strong> tiene stock disponible: <strong>${stockDisponible}</strong><br>Se requiere: <strong>${cantidadRequerida}</strong><br><br>Ingrese la clave de autorización para vender sin Stock`,
                              input: 'password',
                              inputAttributes: {
                                autocapitalize: 'off',
                                autocorrect: 'off'
                              },
                              showCancelButton: true,
                              confirmButtonText: 'Enviar',
                              cancelButtonText: 'Cancelar',
                              showLoaderOnConfirm: true,
                              preConfirm: (clave) => {
                                // Aquí puedes realizar alguna validación de la clave ingresada
                                // Devuelve una promesa que se resolverá o rechazará según la validación
                                return new Promise((resolve:any, reject:any) => {
                                  if (clave == this.apiService.auth_user().codigo_autorizacion) {
                                    resolve();
                                  } else {
                                    reject('Clave incorrecta');
                                  }
                                });
                              },
                              allowOutsideClick: () => !Swal.isLoading()
                            }).then((result) => {
                              if (result.isConfirmed) {
                                this.addDetalle(producto);
                              }
                            }).catch((error) => {
                              Swal.fire('Error', error, 'error');
                            });

                      }else{
                          Swal.fire({
                            title: 'Stock insuficiente',
                            html: `El producto <strong>${producto.nombre || producto.descripcion}</strong> tiene stock disponible: <strong>${stockDisponible}</strong><br>Se requiere: <strong>${cantidadRequerida}</strong><br><br>No hay códigos de autorización configurados. No se puede vender sin stock.`,
                            icon: 'warning',
                            confirmButtonText: 'Aceptar'
                          });
                          return;
                      }
                    }else{
                        // Si la empresa permite vender sin stock, mostrar advertencia pero permitir continuar
                        Swal.fire({
                          title: 'Advertencia de stock',
                          html: `El producto <strong>${producto.nombre || producto.descripcion}</strong> tiene stock disponible: <strong>${stockDisponible}</strong><br>Se requiere: <strong>${cantidadRequerida}</strong><br><br>La venta continuará ya que está permitido vender sin stock.`,
                          icon: 'warning',
                          showCancelButton: true,
                          confirmButtonText: 'Continuar',
                          cancelButtonText: 'Cancelar'
                        }).then((result) => {
                          if (result.isConfirmed) {
                            this.addDetalle(producto);
                          }
                        });
                        return;
                    }
                }
            }
            
            // Si pasa todas las validaciones o es un servicio, agregar el detalle
            this.addDetalle(producto);
        }

        public addDetalle(producto:any){
            this.detalle = Object.assign({}, producto);
            this.detalle.id = null;
            
            // ── Guardar campos de presentación para el backend ───────────────────────
            this.detalle.id_presentacion   = producto.id_presentacion  ?? null;
            this.detalle.factor_conversion = producto.factor_conversion ?? 1;

            // ── Regla de agrupación: AMBOS id_producto + id_presentacion deben coincidir
            // Una "Caja" y una "Unidad suelta" del mismo producto son filas separadas.
            let detalle = null;
            if(this.apiService.auth_user().empresa.agrupar_detalles_venta){
                detalle = this.venta.detalles.find((x:any) =>
                    x.id_producto === this.detalle.id_producto &&
                    (x.id_presentacion ?? null) === (this.detalle.id_presentacion ?? null)
                );
            }
                
            if(detalle) {
                this.detalle = detalle;
                this.detalle.cantidad += producto.cantidad;
            }

            this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);
            
            if(!this.detalle.tipo_gravado){
                this.detalle.tipo_gravado = 'gravada';
            }
            if(!this.detalle.exenta){
                this.detalle.exenta = 0;
            }
            if(!this.detalle.no_sujeta){
                this.detalle.no_sujeta = 0;
            }

            if(!this.detalle.cuenta_a_terceros){
                this.detalle.cuenta_a_terceros = 0;
            }

            // Asegurar que precio_iva existe (para compatibilidad con datos existentes). Usar % del producto.
            if (!this.detalle.precio_iva) {
                const pctDet = this.obtenerPorcentajeIvaDetalle(this.detalle);
                if (pctDet > 0) {
                    this.detalle.precio_iva = (parseFloat(this.detalle.precio) * (1 + pctDet / 100)).toFixed(4);
                } else {
                    this.detalle.precio_iva = this.detalle.precio;
                }
            }

            this.aplicarTipoGravado(this.detalle);

            if(!this.detalle.id_vendedor){
                this.detalle.id_vendedor = this.venta.id_vendedor;
            }

            // Si el producto tiene inventario por lotes (y la empresa tiene lotes activos), igual que v1
            if (producto.inventario_por_lotes && this.apiService.isLotesActivo()) {
                const metodologia = this.getLotesMetodologia();
                if (metodologia === 'Manual') {
                    this.detalle.inventario_por_lotes = true;
                    if (detalle) {
                        limpiarAsignacionLotesDetalle(this.detalle);
                    } else {
                        this.detalle.lote_id = null;
                    }
                    if (!detalle) {
                        this.venta.detalles.push(this.detalle);
                    }
                    this.update.emit(this.venta);
                    setTimeout(() => {
                        this.abrirModalLoteVenta(this.mloteVenta, this.detalle);
                    }, 100);
                    return;
                } else {
                    this.detalle.inventario_por_lotes = true;
                    this.detalle.lote_id = null;
                }
            } else {
                this.detalle.inventario_por_lotes = false;
                this.detalle.lote_id = null;
            }
            
            if(!detalle)
                this.venta.detalles.push(this.detalle);

            this.update.emit(this.venta);
            this.detalle = {};
            if (this.modalRef) { this.modalRef.hide() }
            console.log(this.venta);
        }

    // Métodos para gestión de lotes en ventas
    getLotesMetodologia(): string {
        const empresa = this.apiService.auth_user()?.empresa;
        if (empresa?.custom_empresa?.configuraciones?.lotes_metodologia) {
            return empresa.custom_empresa.configuraciones.lotes_metodologia;
        }
        return 'FIFO';
    }

    public lotes: any[] = [];
    public loteSeleccionado: any = null;
    public detalleConLote: any = null;
    public cantidadObjetivoModal: number = 1;
    private skipLimpiarLotes = false;

    esDetalleCantidadPorLotes(detalle: any): boolean {
        return !!detalle?.inventario_por_lotes
            && this.apiService.isLotesActivo()
            && this.getLotesMetodologia() === 'Manual';
    }

    abrirModalLoteVenta(template: TemplateRef<any>, detalle: any) {
        this.detalleConLote = detalle;
        const previos = detalle.lotes_asignados || [];
        const factor = factorConversionDetalle(detalle);
        this.cantidadObjetivoModal = previos.length
            ? previos.reduce((s: number, p: any) => s + ((parseFloat(String(p.cantidad)) || 0) / (factor || 1)), 0)
            : 1;
        this.cargarLotesDisponiblesVenta();
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    cargarLotesDisponiblesVenta() {
        if (!this.detalleConLote?.id_producto || !this.venta.id_bodega) {
            return;
        }

        this.loading = true;
        this.apiService.getAll('lotes/disponibles', {
            id_producto: this.detalleConLote.id_producto,
            id_bodega: this.venta.id_bodega,
        }).subscribe(lotes => {
            const previos = this.detalleConLote.lotes_asignados || [];
            const factor = factorConversionDetalle(this.detalleConLote);
            this.lotes = (lotes || []).map((lote: any) => {
                const previo = previos.find((p: any) => p.lote_id == lote.id);
                const cantidadBase = previo ? parseFloat(String(previo.cantidad)) || 0 : 0;
                return {
                    ...lote,
                    cantidad_asignada: factor > 0 ? cantidadBase / factor : cantidadBase,
                    stock_unidades: stockBaseAUnidadesDetalle(lote.stock, this.detalleConLote),
                };
            });
            this.loading = false;
        }, error => {
            this.alertService.error(error);
            this.loading = false;
        });
    }

    autoDistribuirLotesVenta(): void {
        const objetivo = parseFloat(String(this.cantidadObjetivoModal)) || 0;
        if (objetivo <= 0) {
            this.alertService.error('Indique una cantidad a distribuir mayor a cero.');
            return;
        }
        autoDistribuirCantidadesLotes(
            this.lotes,
            objetivo * factorConversionDetalle(this.detalleConLote),
            this.detalleConLote
        );
    }

    totalAsignadoLotesVentaUnidades(): number {
        return totalAsignadoUnidadesLotes(this.lotes);
    }

    distribucionLotesValida(): boolean {
        const total = this.totalAsignadoLotesVentaUnidades();
        return total > 0 && !asignacionLotesExcedeStock(this.lotes);
    }

    confirmarDistribucionLotesVenta(): void {
        const factor = factorConversionDetalle(this.detalleConLote);
        const totalUnidades = this.totalAsignadoLotesVentaUnidades();

        if (totalUnidades <= 0) {
            this.alertService.error('Indique al menos un lote con cantidad.');
            return;
        }

        if (asignacionLotesExcedeStock(this.lotes)) {
            this.alertService.error('Alguna cantidad supera el stock disponible del lote.');
            return;
        }

        const asignaciones = this.lotes
            .filter((lote: any) => (parseFloat(String(lote.cantidad_asignada)) || 0) > 0)
            .map((lote: any) => ({
                lote_id: lote.id,
                numero_lote: lote.numero_lote,
                cantidad: (parseFloat(String(lote.cantidad_asignada)) || 0) * factor,
            }));

        this.detalleConLote.lotes_asignados = asignaciones;
        if (asignaciones.length === 1) {
            this.detalleConLote.lote_id = asignaciones[0].lote_id;
            this.detalleConLote.lote = this.lotes.find((l: any) => l.id == asignaciones[0].lote_id);
        } else {
            this.detalleConLote.lote_id = null;
            this.detalleConLote.lote = null;
        }

        this.skipLimpiarLotes = true;
        this.detalleConLote.cantidad = totalUnidades;
        this.updateTotal(this.detalleConLote);
        this.skipLimpiarLotes = false;

        this.modalRef.hide();
        this.update.emit(this.venta);
        this.sumTotal.emit();
    }

    textoLotesDetalle(detalle: any): string {
        return textoResumenLotesDetalle(detalle);
    }

    formatCantidadLote = formatCantidadLote;

    // Eliminar detalle
        public delete(detalle:any){

            Swal.fire({
              title: '¿Estás seguro?',
              text: '¡No podrás revertir esto!',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, eliminarlo',
              cancelButtonText: 'Cancelar'
            }).then((result) => {
              if (result.isConfirmed) {
                let indexAEliminar:any;
                
                    if(detalle.id_paquete){
                        indexAEliminar = this.venta.detalles.findIndex((item:any) => item.id_paquete === detalle.id_paquete);
                    }else{
                        indexAEliminar = this.venta.detalles.findIndex((item:any) => item.id_producto === detalle.id_producto);
                    }
                    if (indexAEliminar !== -1) {
                        if(detalle.id) {
                            this.apiService.delete('venta/detalle/', detalle.id).subscribe(detalle => {
                                this.venta.detalles.splice(indexAEliminar, 1);
                                this.update.emit(this.venta);
                            },error => {this.alertService.error(error); this.loading = false; });
                        }else{
                            this.venta.detalles.splice(indexAEliminar, 1);
                            this.update.emit(this.venta);
                        }

                    }
              } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
              }
            });

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }

    /** Tras activar o desactivar "Con IVA" en la cabecera, recalcula IVA y tipo por línea. */
    public sincronizarIvasDetalles(): void {
        if (!this.venta?.detalles?.length) {
            return;
        }
        sincronizarTipoGravadoPorCobroIva(this.venta.detalles, !!this.venta.cobrar_impuestos);
        for (const detalle of this.venta.detalles) {
            this.aplicarTipoGravado(detalle);
        }
    }

    cambiarOpcion(composicion:any, opcion:any){
        let aux = Object.assign({}, composicion);

        console.log(composicion);
        console.log(opcion);

        composicion.id_compuesto = opcion.id_producto;
        composicion.nombre_compuesto     = opcion.nombre_producto;

        opcion.id_producto  = aux.id_compuesto;
        opcion.nombre_producto      = aux.nombre_compuesto;

        console.log(composicion);
        console.log(opcion);

    }


}
