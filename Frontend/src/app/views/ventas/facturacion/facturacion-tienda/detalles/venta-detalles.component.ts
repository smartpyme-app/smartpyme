import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TiendaVentaBuscadorComponent } from '../buscador/tienda-venta-buscador.component';
import { TiendaVentaProductoComponent } from '../productos/tienda-venta-producto.component';
import { TiendaVentaPaquetesComponent } from '../paquetes/tienda-venta-paquetes.component';
import { TiendaVentaCitasComponent } from '../citas/tienda-venta-citas.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';

import {
    limpiarExentaPorSinIvaSiTipoManual,
    porcentajeIvaDetalle,
    sincronizarTipoGravadoPorCobroIva,
} from '@utils/impuestos-venta.util';
import {
    ORIGEN_STOCK_NORMAL,
    normalizarOrigenStock,
    preguntarOrigenStockSiAplica,
    validarCantidadOrigenConsignaCompra,
    esOrigenConsignaCompra,
} from '@utils/venta-consigna.util';
import {
    autoDistribuirCantidadesLotes,
    asignacionLotesExcedeStock,
    factorConversionDetalle,
    limpiarAsignacionLotesDetalle,
    limpiarLotesSiCambioCantidad,
    stockBaseAUnidadesDetalle,
    textoResumenLotesDetalle,
    totalAsignadoUnidadesLotes,
    formatCantidadLote,
} from '@utils/lotes-venta.util';

import Swal from 'sweetalert2';
import { LazyImageDirective } from '../../../../../directives/lazy-image.directive';
import { FeCrExoneracionDetalleModalComponent } from '@shared/modals/fe-cr-exoneracion-detalle/fe-cr-exoneracion-detalle-modal.component';
import {
  detalleTieneExoneracionCr,
  initFeCrExoneracionDetalle,
} from '@shared/modals/fe-cr-exoneracion-detalle/fe-cr-exoneracion-detalle.util';
import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';
import { copiarImpuestosProductoAlDetalle } from '@utils/impuestos-venta.util';
import { CountryI18nService } from '@services/country-i18n.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';

@Component({
    selector: 'app-venta-detalles',
    templateUrl: './venta-detalles.component.html',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        TiendaVentaBuscadorComponent,
        TiendaVentaProductoComponent,
        TiendaVentaPaquetesComponent,
        TiendaVentaCitasComponent,
        LazyImageDirective,
        FeCrExoneracionDetalleModalComponent,
        CurrencyPipe,
    ],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VentaDetallesComponent extends BaseModalComponent implements OnInit {

  private readonly countryI18n = inject(CountryI18nService);

  @Input() venta: any = {};
  @Input() usuarios: any = {};
  /** Desde facturación: al activar, muestra en cada línea el input Cta. terceros junto a Precio. */
  @Input() habilitarCuentaTerceros = false;
  @Input() customFields: any = {};
  @Input() selectedCustomFields: number[] = [];
  @Input() cotizacion: number = 0;
  @Input() mode: "create" | "edit" | "show" = "create";
  /** Si no se pasa, se infiere del país FE de la empresa. */
  @Input() esFeCostaRica: boolean | null = null;
  public usuario: any = {};
  public detalle: any = {};
  public composicion: any = {};
  public supervisor: any = {};

  @Output() update = new EventEmitter();
  @Output() sumTotal = new EventEmitter();
  @Output() alMenosUnPaqueteConCuentaTerceros = new EventEmitter<void>();
  @Output() selectCliente = new EventEmitter<any>();
  override modalRef!: BsModalRef;
  public zoomImageUrl: string = '';

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    @ViewChild('mloteVenta')
    public mloteVenta!: TemplateRef<any>;

    @ViewChild('feCrExoneracionModal')
    feCrExoneracionModal!: FeCrExoneracionDetalleModalComponent;

  public buscador: string = '';
  public override loading: boolean = false;

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private cdr: ChangeDetectorRef
  ) {
    super(modalManager, alertService);
  }

  ngOnInit() {
    this.usuario = this.apiService.auth_user();
  }

  get esFeCostaRicaActivo(): boolean {
    if (this.esFeCostaRica != null) {
      return this.esFeCostaRica;
    }
    return resolveCodigoPaisFe(this.usuario?.empresa) === FE_PAIS_CR;
  }

  readonly detalleTieneExoneracionCr = detalleTieneExoneracionCr;

  get editTaxExemptionTitle(): string {
    return this.countryI18n.tax('editTaxExemptionTitle');
  }

  get configureTaxExemptionTitle(): string {
    return this.countryI18n.tax('configureTaxExemptionTitle');
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

  openModalEdit(template: TemplateRef<any>, detalle: any) {
    this.detalle = detalle;
    this.openModal(template, { class: 'modal-md', backdrop: 'static' });
  }

    private obtenerPorcentajeIvaDetalle(detalle: any): number {
        return porcentajeIvaDetalle(
            detalle,
            this.apiService.auth_user()?.empresa?.iva,
            !!this.venta.cobrar_impuestos,
            this.apiService.auth_user()?.empresa?.pais
        );
    }

    /** Aplica gravada/exenta/no_sujeta; IVA alineado con total con IVA redondeado por línea. */
    private aplicarTipoGravado(detalle: any) {
        const total = parseFloat(detalle.total) || 0;
        detalle.gravada = 0;
        detalle.exenta = 0;
        detalle.no_sujeta = 0;
        const tipo = (detalle.tipo_gravado || 'gravada').toLowerCase();
        if (tipo === 'gravada') {
            detalle.gravada = total;
            const pct = this.obtenerPorcentajeIvaDetalle(detalle);
            detalle.iva = pct > 0 ? parseFloat((total * (pct / 100)).toFixed(4)) : 0;
        } else if (tipo === 'exenta') {
            detalle.exenta = total;
            detalle.iva = 0;
        } else if (tipo === 'exonerada') {
            detalle.gravada = total;
            detalle.iva = 0;
        } else {
            detalle.no_sujeta = total;
            detalle.iva = 0;
        }
    }

    public updateTotal(detalle:any){
        const aplicar = () => {
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
        };

        if (esOrigenConsignaCompra(detalle.origen_stock)) {
            validarCantidadOrigenConsignaCompra(this.apiService, this.alertService, this.venta, detalle)
                .subscribe((ok) => {
                    if (ok) {
                        aplicar();
                    }
                });
            return;
        }

        aplicar();
    }

    /** Recalcula totales; limpia lotes solo si el usuario cambió la cantidad. */
    public onCantidadChange(detalle: any): void {
        limpiarLotesSiCambioCantidad(detalle, {
            skipLimpiarLotes: this.skipLimpiarLotes,
            metodologiaManual: this.getLotesMetodologia() === 'Manual',
        });
        this.updateTotal(detalle);
    }

    public onTipoGravadoChange(detalle: any) {
        const tipo = (detalle.tipo_gravado || '').toLowerCase();
        if (this.esFeCostaRicaActivo && tipo === 'exonerada') {
            initFeCrExoneracionDetalle(detalle);
            if (!detalle.fe_cr_exoneracion?.aplica) {
                detalle.fe_cr_exoneracion.aplica = true;
            }
        } else if (detalle.fe_cr_exoneracion?.aplica && tipo !== 'exonerada') {
            detalle.fe_cr_exoneracion.aplica = false;
        }
        limpiarExentaPorSinIvaSiTipoManual(detalle);
        this.aplicarTipoGravado(detalle);
        this.update.emit(this.venta);
        this.sumTotal.emit();
        this.cdr.markForCheck();
    }

    abrirModalExoneracionCr(detalle: any): void {
        this.feCrExoneracionModal.abrir(detalle);
    }

    onExoneracionDetalleSaved(detalle: any): void {
        this.aplicarTipoGravado(detalle);
        this.update.emit(this.venta);
        this.sumTotal.emit();
        this.cdr.markForCheck();
    }

  public modalSupervisor(detalle: any) {
    this.detalle = detalle;
    this.openModal(this.supervisorTemplate, { class: 'modal-xs' });
  }

  public openModalCompuesto(template: TemplateRef<any>, composicion: any) {
    this.composicion = composicion;
    this.openModal(template, { class: 'modal-md', backdrop: 'static' });
  }

  public supervisorCheck() {
    this.loading = true;
    this.cdr.markForCheck();
    this.apiService.store('usuario-validar', this.supervisor)
        .pipe(this.untilDestroyed())
        .subscribe(supervisor => {
      if (this.modalRef) {
        this.closeModal();
      }
      this.delete(this.detalle);
      this.loading = false;
      this.supervisor = {};
      this.cdr.markForCheck();
    }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
  }

  // Agregar detalle
  productoSelect(producto: any): void {
    preguntarOrigenStockSiAplica(this.apiService, this.venta, producto).subscribe((origen) => {
      if (origen === null) {
        return;
      }
      const productoConOrigen = { ...producto, origen_stock: origen };
      if (esOrigenConsignaCompra(origen)) {
        validarCantidadOrigenConsignaCompra(this.apiService, this.alertService, this.venta, productoConOrigen)
          .subscribe((ok) => {
            if (ok) {
              this.procesarProductoSelect(productoConOrigen);
            }
          });
        return;
      }
      this.procesarProductoSelect(productoConOrigen);
    });
  }

  private procesarProductoSelect(producto: any): void {
            if (producto.tipo != 'Servicio' && producto.stock !== null && producto.stock !== undefined) {
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

  public addDetalle(producto: any) {
    this.detalle = Object.assign({}, producto);
    this.detalle.id = null;

            copiarImpuestosProductoAlDetalle(
      this.detalle,
      producto,
      this.apiService.auth_user()?.empresa?.iva ?? 0
    );

    this.detalle.id_presentacion = producto.id_presentacion ?? null;
    this.detalle.factor_conversion = producto.factor_conversion ?? 1;
    this.detalle.origen_stock = normalizarOrigenStock(producto.origen_stock ?? ORIGEN_STOCK_NORMAL);

    let detalle = null;
    if (this.apiService.auth_user().empresa.agrupar_detalles_venta) {
      detalle = this.venta.detalles.find((x: any) =>
        x.id_producto === this.detalle.id_producto &&
        (x.id_presentacion ?? null) === (this.detalle.id_presentacion ?? null) &&
        normalizarOrigenStock(x.origen_stock) === this.detalle.origen_stock
      );
    }

    if (detalle) {
      this.detalle = detalle;
      this.detalle.cantidad += producto.cantidad;
    }

            this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);

            if(!this.detalle.tipo_gravado){
                this.detalle.tipo_gravado = 'gravada';
            }
            if (this.esFeCostaRicaActivo) {
                initFeCrExoneracionDetalle(this.detalle);
            }
            if(!this.detalle.exenta){
                this.detalle.exenta = 0;
            }
            if(!this.detalle.no_sujeta){
                this.detalle.no_sujeta = 0;
            }

    if (!this.detalle.cuenta_a_terceros) {
      this.detalle.cuenta_a_terceros = 0;
    }

            if (!this.detalle.precio_iva) {
                const pctDet = this.obtenerPorcentajeIvaDetalle(this.detalle);
                if (pctDet > 0) {
                    this.detalle.precio_iva = (parseFloat(this.detalle.precio) * (1 + pctDet / 100)).toFixed(4);
                } else {
                    this.detalle.precio_iva = this.detalle.precio;
                }
            }

            const precioSinIva = parseFloat(this.detalle.precio || 0);
            this.detalle.sub_total = Number((parseFloat(this.detalle.cantidad) * precioSinIva).toFixed(4));
            if(!this.detalle.total || detalle){
                this.detalle.total = (parseFloat(this.detalle.sub_total) - parseFloat(this.detalle.descuento || 0)).toFixed(4);
            }

            this.aplicarTipoGravado(this.detalle);

            if(!this.detalle.id_vendedor){
                this.detalle.id_vendedor = this.venta.id_vendedor;
            }

            // Si el producto tiene inventario por lotes (y la empresa tiene lotes activos)
            if (producto.inventario_por_lotes && this.apiService.isLotesActivo()) {
                const metodologia = this.getLotesMetodologia();
                if (metodologia === 'Manual') {
                    // Si es manual, abrir modal para seleccionar lote
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
                    // Abrir modal automáticamente para seleccionar lote
                    setTimeout(() => {
                        this.abrirModalLoteVenta(this.mloteVenta, this.detalle);
                    }, 100);
                    return;
                } else {
                    // Si es automático, el backend se encargará de seleccionar el lote
                    this.detalle.inventario_por_lotes = true;
                    this.detalle.lote_id = null; // Se asignará automáticamente en el backend
                }
            } else {
                this.detalle.inventario_por_lotes = false;
                this.detalle.lote_id = null;
            }

            if(!detalle)
                this.venta.detalles.push(this.detalle);

    this.cdr.markForCheck();
    this.update.emit(this.venta);
    this.detalle = {};
    if (this.modalRef) {
      this.closeModal();
    }
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
        this.openLargeModal(template);
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

        this.closeModal();
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
        let indexAEliminar: any;

        if (detalle.id_paquete) {
          indexAEliminar = this.venta.detalles.findIndex((item: any) => item.id_paquete === detalle.id_paquete);
        } else {
          indexAEliminar = this.venta.detalles.findIndex((item: any) => item.id_producto === detalle.id_producto);
        }
        if (indexAEliminar !== -1) {
          if (detalle.id) {
            console.log('venta', this.venta);
            const endpoint = this.venta.cotizacion == 1 ? 'cotizacion-venta-detalle' : 'venta-detalle';

            this.apiService.delete(endpoint + '/', detalle.id)
                .pipe(this.untilDestroyed())
                .subscribe(detalle => {
              this.venta.detalles.splice(indexAEliminar, 1);
              this.cdr.markForCheck();
              this.update.emit(this.venta);
            }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
          } else {
            this.venta.detalles.splice(indexAEliminar, 1);
            this.cdr.markForCheck();
            this.update.emit(this.venta);
          }

        }
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });

  }

  public sumTotalEmit() {
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
    composicion.nombre_compuesto = opcion.nombre_producto;

    opcion.id_producto = aux.id_compuesto;
    opcion.nombre_producto = aux.nombre_compuesto;

    console.log(composicion);
    console.log(opcion);

  }

  getColumnCount(): number {
    let count = 5; // Base columns (Product, Quantity, Price, Discount, Total, Actions)
    if (this.usuario.empresa.vendedor_detalle_venta) count++;
    count += this.selectedCustomFields.length;
    return count;
  }

  public hasImage(img: any): boolean {
      return !!img && img !== 'default.png' && img !== 'default.jpg' && img !== 'productos/default.jpg' && img !== 'null' && img !== 'undefined';
  }

  public zoomImage(img: any, dialog: any) {
      if (this.hasImage(img)) {
          this.zoomImageUrl = this.apiService.baseUrl + '/img/' + img;
          dialog.showModal();
      }
  }

}
