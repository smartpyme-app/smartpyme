import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ModalModule } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { NgChartsModule } from 'ng2-charts';
import { TagInputModule } from 'ngx-chips';
import { NgSelectModule } from '@ng-select/ng-select';
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask';
import { ReactiveFormsModule } from '@angular/forms';

import { InventarioRoutingModule } from './inventario.routing.module';

import { ProductosComponent } from './productos/productos.component';
import { ProductoComponent } from './productos/producto/producto.component';
import { ProductoInformacionComponent } from './productos/producto/informacion/producto-informacion.component';
import { ProductoInventariosComponent } from './productos/producto/inventario/producto-inventarios.component';
import { ProductoSucursalesComponent } from './productos/producto/sucursales/producto-sucursales.component';
import { ProductoProveedoresComponent } from './productos/producto/proveedores/producto-proveedores.component';
import { ProductoComposicionComponent } from './productos/producto/composicion/producto-composicion.component';
import { ProductoPromocionesComponent } from './productos/producto/promociones/producto-promociones.component';
import { ProductoImagenesComponent } from './productos/producto/imagenes/producto-imagenes.component';
import { ProductoPreciosComponent } from './productos/producto/precios/producto-precios.component';
import { ProductoComprasComponent } from './productos/producto/historial/compras/producto-compras.component';
import { ProductoAjustesComponent } from './productos/producto/historial/ajustes/producto-ajustes.component';
import { ProductoVentasComponent } from './productos/producto/historial/ventas/producto-ventas.component';
import { PromocionesComponent } from './promociones/promociones.component';
import { ProductoComboComponent } from './productos/producto/combo/producto-combo.component';
import { ComboDetallesComponent } from './productos/producto/combo/detalles/combo-detalles.component';
import { BuscadorProductoComponent } from './productos/producto/combo/buscador-producto/buscador-producto.component';

import { ProductosConsignasComponent } from './consignas/productos-consignas.component';

import { MateriasPrimaComponent } from './materias-prima/materias-prima.component';
import { MateriaPrimaComponent } from './materias-prima/materia-prima/materia-prima.component';
import { MateriaPrimaInformacionComponent } from './materias-prima/materia-prima/informacion/materia-prima-informacion.component';

import { KardexComponent } from './kardex/kardex.component';
import { TrasladosComponent } from './traslados/traslados.component';
import { TrasladoComponent } from './traslados/traslado/traslado.component';
import { AjustesComponent } from './ajustes/ajustes.component';
import { AjusteComponent } from './ajustes/ajuste/ajuste.component';

import { ServiciosComponent } from './servicios/servicios.component';

import { CategoriasComponent } from './categorias/categorias.component';
import { CategoriaCuentasComponent } from './categorias/cuentas/categoria-cuentas.component';
import { SubCategoriasComponent } from './categorias/subcategorias/subcategorias.component';

import { BodegaComponent } from './bodegas/bodega/bodega.component';
import { BodegasComponent } from './bodegas/bodegas.component';
import { TrasladoMasivoComponent } from './productos/producto/traslado/traslado-masivo.component';

import { AjusteMasivoComponent } from './productos/producto/ajuste/ajuste-masivo.component';

import { ComboIndexComponent } from './productos/producto/combo/combo-index/combo-index.component';
import { VerProductoComponent } from './productos/producto/ver-producto/ver-producto.component';
import { CustomFieldsComponent } from './custom-fields/custom-fields.component';

// Nuevos componentes de entradas y salidas
import { InventarioEntradasComponent } from './entradas/inventario-entradas.component';
import { InventarioSalidasComponent } from './salidas/inventario-salidas.component';
import { InventarioEntradaComponent } from './entradas/entrada/inventario-entrada.component';
import { InventarioSalidaComponent } from './salidas/salida/inventario-salida.component';
import { EntradaDetalleComponent } from './entradas/entrada-detalle/entrada-detalle.component';
import { SalidaDetalleComponent } from './salidas/salida-detalle/salida-detalle.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    TagInputModule,
    NgChartsModule,
    NgSelectModule,
    NgxMaskDirective, NgxMaskPipe,
    InventarioRoutingModule,
    ReactiveFormsModule,
    TabsModule.forRoot(),
    TooltipModule.forRoot(),
    ModalModule.forRoot(),
    PopoverModule.forRoot(),
    // Componentes standalone
    ProductosComponent,
    ProductoComponent,
    KardexComponent,
    PromocionesComponent,
    ProductoInformacionComponent,
    ProductoInventariosComponent,
    ProductoSucursalesComponent,
    ProductoProveedoresComponent,
    ProductoComposicionComponent,
    ProductoImagenesComponent,
    ProductoPromocionesComponent,
    ProductoPreciosComponent,
    ProductoComprasComponent,
    ProductoAjustesComponent,
    ProductoVentasComponent,
    ProductosConsignasComponent,
    MateriasPrimaComponent,
    MateriaPrimaComponent,
    MateriaPrimaInformacionComponent,
    TrasladosComponent,
    TrasladoComponent,
    AjustesComponent,
    AjusteComponent,
    ServiciosComponent,
    CategoriasComponent,
    CategoriaCuentasComponent,
    SubCategoriasComponent,
    BodegaComponent,
    BodegasComponent,
    ProductoComboComponent,
    ComboDetallesComponent,
    BuscadorProductoComponent,
    ComboIndexComponent,
    VerProductoComponent,
    CustomFieldsComponent,
    TrasladoMasivoComponent,
    AjusteMasivoComponent,
    // Nuevos componentes
    InventarioEntradasComponent,
    InventarioSalidasComponent,
    InventarioEntradaComponent,
    InventarioSalidaComponent,
    EntradaDetalleComponent,
    SalidaDetalleComponent
  ],
  exports: [
    ProductosComponent,
    ProductoComponent,
    KardexComponent,
    PromocionesComponent,
    ProductoInformacionComponent,
    ProductoInventariosComponent,
    ProductoSucursalesComponent,
    ProductoProveedoresComponent,
    ProductoComposicionComponent,
    ProductoImagenesComponent,
    ProductoPromocionesComponent,
    ProductoPreciosComponent,
    ProductoComprasComponent,
    ProductoAjustesComponent,
    ProductoVentasComponent,
    ProductosConsignasComponent,
    MateriasPrimaComponent,
    MateriaPrimaComponent,
    MateriaPrimaInformacionComponent,
    TrasladosComponent,
    TrasladoComponent,
    AjustesComponent,
    AjusteComponent,
    ServiciosComponent,
    CategoriasComponent,
    CategoriaCuentasComponent,
    SubCategoriasComponent,
    BodegaComponent,
    BodegasComponent,
    ProductoComboComponent,
    ComboDetallesComponent,
    BuscadorProductoComponent,
    ComboIndexComponent,
    VerProductoComponent,
    CustomFieldsComponent,
    TrasladoMasivoComponent,
    AjusteMasivoComponent,
    // Nuevos componentes
    InventarioEntradasComponent,
    InventarioSalidasComponent,
    InventarioEntradaComponent,
    InventarioSalidaComponent,
    EntradaDetalleComponent,
    SalidaDetalleComponent
  ]
})
export class InventarioModule { }

