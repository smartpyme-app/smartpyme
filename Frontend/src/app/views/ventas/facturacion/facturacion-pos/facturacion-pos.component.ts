import { Component, inject, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, ActivatedRoute, Router } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import { RestauranteService } from '@services/restaurante.service';
import { FidelizacionService } from '@services/fidelizacion.service';
import { GiftCardsService } from '@services/gift-cards.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { PosMenuVentasProducto, PosMenuVentasService } from '@services/pos-menu-ventas.service';
import { SumPipe } from '@pipes/sum.pipe';
import { FilterPipe } from '@pipes/filter.pipe';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { BuscadorClientesComponent } from '@shared/parts/buscador-clientes/buscador-clientes.component';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';
import { MetodosDePagoComponent } from '../facturacion-tienda/metodos-de-pago/metodos-de-pago.component';
import { VentaDetallesV2Component } from '../facturacion-tienda-v2/detalles/venta-detalles-v2.component';
import { FacturacionV2Component } from '../facturacion-tienda-v2/facturacion-v2.component';
import {
  armarDetalleDesdeProductoV2,
  ProductoDetalleV2MapperCtx,
} from '../facturacion-tienda-v2/utils/producto-detalle-v2.mapper';
import { PosCatalogoVentasComponent } from './pos-catalogo-ventas/pos-catalogo-ventas.component';
import { PosTicketVentasComponent } from './pos-ticket-ventas/pos-ticket-ventas.component';
import { PosOpcionesAvanzadasComponent } from './pos-opciones-avanzadas/pos-opciones-avanzadas.component';
import { PosLineaVentaSheetComponent } from './pos-linea-venta-sheet/pos-linea-venta-sheet.component';
import { contarOpcionesAvanzadasActivas } from './pos-opciones-avanzadas/pos-opciones.util';
import { SharedModule } from '@shared/shared.module';

@Component({
  selector: 'app-facturacion-pos',
  standalone: true,
  templateUrl: './facturacion-pos.component.html',
  styleUrls: ['./facturacion-pos.component.css'],
  imports: [
    CommonModule,
    FormsModule,
    NgSelectModule,
    RouterModule,
    CurrencyPipe,
    FilterPipe,
    SharedModule,
    BuscadorClientesComponent,
    CrearClienteComponent,
    MetodosDePagoComponent,
    VentaDetallesV2Component,
    PosCatalogoVentasComponent,
    PosTicketVentasComponent,
    PosOpcionesAvanzadasComponent,
    PosLineaVentaSheetComponent,
  ],
  providers: [SumPipe],
})
export class FacturacionPosComponent extends FacturacionV2Component {
  private posMenuVentas = inject(PosMenuVentasService);
  private posSumPipe = inject(SumPipe);
  private posModalService = inject(BsModalService);
  private posAlert = inject(AlertService);

  modalOpcionesRef?: BsModalRef;
  modalLineaRef?: BsModalRef;
  modalPresentacionRef?: BsModalRef;
  detalleEditando: any = null;
  presentacionesPendientes: any[] = [];
  productoPendientePresentacion: any = null;
  tilePendientePresentacion: PosMenuVentasProducto | null = null;

  @ViewChild('ventaDetallesPos')
  protected override ventaDetallesV2?: VentaDetallesV2Component;

  constructor() {
    super(
      inject(ApiService),
      inject(FacturacionElectronicaService),
      inject(AlertService),
      inject(BsModalService),
      inject(SumPipe),
      inject(ActivatedRoute),
      inject(Router),
      inject(FuncionalidadesService),
      inject(RestauranteService),
      inject(FidelizacionService),
      inject(GiftCardsService),
      inject(CountryI18nService),
    );
  }

  contarOpcionesAvanzadasActivas(): number {
    return contarOpcionesAvanzadasActivas(this.venta, this.habilitarCuentaTerceros);
  }

  abrirOpcionesAvanzadas(template: TemplateRef<any>): void {
    this.modalOpcionesRef = this.posModalService.show(template, { class: 'modal-lg pos-opciones-sheet', backdrop: 'static' });
  }

  cerrarOpcionesAvanzadas(): void {
    this.sumTotal();
    this.modalOpcionesRef?.hide();
  }

  /** ponytail: getter para pasar el host al modal de opciones (Angular no permite `this` en template). */
  get posHost(): FacturacionPosComponent {
    return this;
  }

  readonly formatNumberFn = (v: number) => this.formatNumber(v);
  readonly esLineaConLoteFn = (d: any) => this.esLineaConLote(d);

  onProductoCatalogo(tile: PosMenuVentasProducto): void {
    const params: { id_bodega?: number; id_presentacion?: number } = {
      id_bodega: this.venta.id_bodega,
    };
    if (tile.id_presentacion) {
      params.id_presentacion = tile.id_presentacion;
    }
    this.posMenuVentas.productoParaVenta(tile.id, params).subscribe({
      next: (producto) => {
        const pres = producto?.presentaciones;
        if (!tile.id_presentacion && Array.isArray(pres) && pres.length > 1) {
          this.tilePendientePresentacion = tile;
          this.productoPendientePresentacion = producto;
          this.presentacionesPendientes = pres;
          this.modalPresentacionRef = this.posModalService.show(this.presentacionPickerTpl, {
            class: 'modal-sm',
            backdrop: 'static',
          });
          return;
        }
        this.agregarProductoDesdeTile(producto, tile);
      },
      error: (err) => this.posAlert.error(err),
    });
  }

  @ViewChild('presentacionPickerTpl')
  presentacionPickerTpl!: TemplateRef<any>;

  elegirPresentacion(pres: any): void {
    const tile = this.tilePendientePresentacion;
    const producto = this.productoPendientePresentacion;
    this.modalPresentacionRef?.hide();
    this.tilePendientePresentacion = null;
    this.productoPendientePresentacion = null;
    this.presentacionesPendientes = [];
    if (!tile || !producto) {
      return;
    }
    const tileConPres: PosMenuVentasProducto = {
      ...tile,
      id_presentacion: pres.id,
      nombre: pres.nombre || tile.nombre,
    };
    this.agregarProductoDesdeTile(producto, tileConPres, pres);
  }

  private agregarProductoDesdeTile(producto: any, tile: PosMenuVentasProducto, pres?: any): void {
    const preparado = this.prepararProductoDesdeTile(producto, tile, pres);
    const det = armarDetalleDesdeProductoV2(preparado, this.mapperCtx());
    this.ventaDetallesV2?.productoSelect(det);
  }

  private prepararProductoDesdeTile(producto: any, tile: PosMenuVentasProducto, presOverride?: any): any {
    const pres = presOverride
      ?? producto.presentaciones?.find((p: any) => p.id === tile.id_presentacion);
    if (pres) {
      return {
        ...producto,
        id_producto: producto.id,
        id_presentacion: pres.id,
        nombre_mostrar: pres.nombre || tile.nombre,
        precio: pres.precio_venta,
        factor_conversion: pres.factor_conversion ?? 1,
      };
    }
    if (tile.id_presentacion) {
      return {
        ...producto,
        id_producto: producto.id,
        id_presentacion: tile.id_presentacion,
        nombre_mostrar: tile.nombre,
      };
    }
    return producto;
  }

  private mapperCtx(): ProductoDetalleV2MapperCtx {
    const user = this.apiService.auth_user();
    return {
      ivaEmpresa: Number(user?.empresa?.iva ?? 0),
      valorInventarioPromedio: user.empresa.valor_inventario === 'promedio',
      lotesActivo: this.apiService.isLotesActivo(),
      idBodega: this.venta?.id_bodega,
      sumStock: (items, field) => parseFloat(this.posSumPipe.transform(items, field)),
      getNombreCompleto: (p) => p.nombre_mostrar || p.nombre,
    };
  }

  onCantidadDelta(event: { detalle: any; delta: number }): void {
    const detalle = event.detalle;
    const next = (parseFloat(detalle.cantidad) || 0) + event.delta;
    if (next <= 0) {
      this.ventaDetallesV2?.delete(detalle);
      return;
    }
    detalle.cantidad = next;
    this.ventaDetallesV2?.updateTotal(detalle);
    this.sumTotal();
  }

  onEliminarLinea(detalle: any): void {
    this.ventaDetallesV2?.delete(detalle);
  }

  abrirLineaSheet(detalle: any, template: TemplateRef<any>): void {
    this.detalleEditando = detalle;
    this.modalLineaRef = this.posModalService.show(template, { class: 'modal-sm pos-linea-sheet', backdrop: 'static' });
  }

  guardarLineaSheet(): void {
    if (this.detalleEditando) {
      this.ventaDetallesV2?.updateTotal(this.detalleEditando);
      this.ventaDetallesV2?.onTipoGravadoChange(this.detalleEditando);
      this.sumTotal();
    }
    this.cerrarLineaSheet();
  }

  cerrarLineaSheet(): void {
    this.modalLineaRef?.hide();
    this.detalleEditando = null;
  }

  abrirLoteLinea(detalle: any): void {
    const vd = this.ventaDetallesV2;
    if (vd?.mloteVenta) {
      vd.abrirModalLoteVenta(vd.mloteVenta, detalle);
    }
  }

  esLineaConLote(detalle: any): boolean {
    return this.ventaDetallesV2?.esDetalleCantidadPorLotes(detalle) ?? false;
  }

  mostrarTipoGravadoLinea(): boolean {
    return !!this.apiService.auth_user()?.empresa?.cambiar_tipo_impuesto_venta;
  }
}
