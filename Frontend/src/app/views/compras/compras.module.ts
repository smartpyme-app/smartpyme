import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { FocusModule } from 'angular2-focus';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';

import { ComprasRoutingModule } from './compras.routing.module';

import { ComprasComponent } from './compras.component';
import { CompraComponent } from './compra/compra.component';
import { FacturacionCompraComponent } from './facturacion/facturacion-compra.component';
import { FacturacionCompraConsignaComponent } from './facturacion/facturacion-consigna/facturacion-compra-consigna.component';
import { CompraProductoComponent } from './facturacion/compra-producto/compra-producto.component';
import { CompraDetallesComponent } from './facturacion/detalles/compra-detalles.component';

import { ComprasRecurrentesComponent } from './recurrentes/compras-recurrentes.component';
import { AbonosComprasComponent } from './abonos/abonos-compras.component';

import { DevolucionesComprasComponent } from './devoluciones/devoluciones-compras.component';
import { DevolucionCompraComponent } from './devoluciones/devolucion/devolucion-compra.component';

import { DevolucionCompraNuevaComponent } from './devoluciones/devolucion-nueva/devolucion-compra-nueva.component';
import { DevolucionCompraDetallesComponent } from './devoluciones/devolucion-nueva/detalles/devolucion-compra-detalles.component';
import { CotizacionesComprasComponent } from './cotizaciones/cotizaciones-compras.component';

import { GastosComponent } from './gastos/gastos.component';
import { GastosRecurrentesComponent } from './gastos/recurrentes/gastos-recurrentes.component';
import { GastoComponent } from './gastos/gasto/gasto.component';
import { GastoDetallesComponent } from './gastos/gasto-detalles/gasto-detalles.component';
import { GastosCategoriasComponent } from './gastos/categorias/gastos-categorias.component';
import { GastosDashComponent } from './gastos/dash/gastos-dash.component';
import { RetaceoComponent } from './retaceo/retaceo.component';
import { OrdenCompraFormComponent } from './cotizaciones/components/orden-compra-form/orden-compra-form.component';
import { RetaceosListComponent } from './retaceo/retaceos-list.component';

// import { HistorialComprasComponent } from './reportes/historial/historial-compras.component';
// import { DetalleComprasComponent } from './reportes/detalle/detalle-compras.component';
// import { CategoriasComprasComponent } from './reportes/categorias/categorias-compras.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    ReactiveFormsModule,
    PipesModule,
    SharedModule,
    NgSelectModule,
    ComprasRoutingModule,
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    FocusModule.forRoot()
  ],
  declarations: [
    ComprasComponent,
    CompraComponent,
    ComprasRecurrentesComponent,
    AbonosComprasComponent,
    FacturacionCompraComponent,
    FacturacionCompraConsignaComponent,
    CompraProductoComponent,
    CompraDetallesComponent,
    DevolucionesComprasComponent,
    DevolucionCompraComponent,
    DevolucionCompraNuevaComponent,
    DevolucionCompraDetallesComponent,
    CotizacionesComprasComponent,
    GastosComponent,
    GastosRecurrentesComponent,
    GastoComponent,
    GastoDetallesComponent,
    GastosDashComponent,
    GastosCategoriasComponent,
    RetaceoComponent,
    OrdenCompraFormComponent,
    RetaceosListComponent
  ],
  exports: [
    ComprasComponent,
    CompraComponent,
    ComprasRecurrentesComponent,
    AbonosComprasComponent,
    FacturacionCompraComponent,
    FacturacionCompraConsignaComponent,
    CompraProductoComponent,
    CompraDetallesComponent,
    DevolucionesComprasComponent,
    DevolucionCompraComponent,
    DevolucionCompraNuevaComponent,
    DevolucionCompraDetallesComponent,
    CotizacionesComprasComponent,
    GastosComponent,
    GastosRecurrentesComponent,
    GastoComponent,
    GastoDetallesComponent,
    GastosDashComponent,
    GastosCategoriasComponent,
    RetaceoComponent,
    RetaceosListComponent
  ]
})
export class ComprasModule { }
