import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { FocusModule } from 'angular2-focus';
import { PipesModule } from '../../pipes/pipes.module';
import { SharedModule } from '../../shared/shared.module';
import { ComprasComponent } from './compras.component';
import { NgSelectModule } from '@ng-select/ng-select';

import { ComprasRoutingModule } from './compras.routing.module';

import { CompraComponent } from './compra/compra.component';
import { CompraProductoComponent } from './compra/compra-producto/compra-producto.component';
import { CompraDetallesComponent } from './compra/detalles/compra-detalles.component';

import { DevolucionesComprasComponent } from './devoluciones/devoluciones-compras.component';
import { DevolucionCompraComponent } from './devoluciones/devolucion/devolucion-compra.component';

import { DevolucionCompraNuevaComponent } from '../../views/compras/devoluciones/devolucion-nueva/devolucion-compra-nueva.component';
import { DevolucionCompraDetallesComponent } from '../../views/compras/devoluciones/devolucion-nueva/detalles/devolucion-compra-detalles.component';
import { RequisicionesComprasComponent } from './requisiciones/requisiciones-compras.component';

import { GastosComponent } from './gastos/gastos.component';
import { GastoComponent } from './gastos/gasto/gasto.component';
import { GastosCategoriasComponent } from './gastos/categorias/gastos-categorias.component';
import { GastosDashComponent } from './gastos/dash/gastos-dash.component';

// import { HistorialComprasComponent } from './reportes/historial/historial-compras.component';
// import { DetalleComprasComponent } from './reportes/detalle/detalle-compras.component';
// import { CategoriasComprasComponent } from './reportes/categorias/categorias-compras.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
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
    CompraProductoComponent,
    CompraDetallesComponent,
    DevolucionesComprasComponent,
    DevolucionCompraComponent,
    DevolucionCompraNuevaComponent,
    DevolucionCompraDetallesComponent,
    RequisicionesComprasComponent,
    GastosComponent,
    GastoComponent,
    GastosDashComponent,
    GastosCategoriasComponent,
  ],
  exports: [
  	ComprasComponent,
    CompraComponent,
    CompraProductoComponent,
    CompraDetallesComponent,
    DevolucionesComprasComponent,
    DevolucionCompraComponent,
    DevolucionCompraNuevaComponent,
    DevolucionCompraDetallesComponent,
    RequisicionesComprasComponent,
    GastosComponent,
    GastoComponent,
    GastosDashComponent,
    GastosCategoriasComponent,
  ]
})
export class ComprasModule { }
