import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { FocusModule } from 'angular2-focus';
import { PipesModule } from '../../../pipes/pipes.module';
import { SharedModule } from '../../../shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';

import { FacturacionComponent } from './facturacion.component';
import { FacturacionTiendaComponent } from './facturacion-tienda/facturacion-tienda.component';
import { CodigoBarraComponent } from './facturacion-tienda/codigo-barra/codigo-barra.component';
import { VentaComboComponent } from './facturacion-tienda/combos/venta-combo.component';
import { VentasPendientesComponent } from './facturacion-tienda/pendientes/ventas-pendientes.component';
import { VentasFletesComponent } from './facturacion-tienda/fletes/ventas-fletes.component';
import { VentaRecargasComponent } from './facturacion-tienda/recargas/venta-recargas.component';
import { TiendaVentaProductoComponent } from './facturacion-tienda/productos/tienda-venta-producto.component';
import { VentaDetallesComponent } from './facturacion-tienda/detalles/venta-detalles.component';

import { FacturacionGasComponent } from './facturacion-gas/facturacion-gas.component';
import { VentaGasolinaComponent } from './facturacion-gas/gasolina/venta-gasolina.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    NgSelectModule,
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    ModalModule.forRoot(),
    FocusModule.forRoot()
  ],
  declarations: [
    FacturacionComponent,
    FacturacionTiendaComponent,
    CodigoBarraComponent,
    TiendaVentaProductoComponent,
    VentaComboComponent,
    VentasPendientesComponent,
    VentasFletesComponent,
    VentaRecargasComponent,
    VentaDetallesComponent,
    FacturacionGasComponent,
    VentaGasolinaComponent
  ],
  exports: [
    FacturacionComponent,
    FacturacionTiendaComponent,
    CodigoBarraComponent,
    TiendaVentaProductoComponent,
    VentaComboComponent,
    VentasPendientesComponent,
    VentasFletesComponent,
    VentaRecargasComponent,
    VentaDetallesComponent,
    FacturacionGasComponent,
    VentaGasolinaComponent
  ]
})
export class FacturacionModule { }
