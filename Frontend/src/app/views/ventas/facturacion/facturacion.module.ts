import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ReactiveFormsModule } from '@angular/forms';

import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { FocusModule } from 'angular2-focus';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';

import { FacturacionComponent } from './facturacion-tienda/facturacion.component';
import { FacturacionConsignaComponent } from './facturacion-consigna/facturacion-consigna.component';
import { TiendaVentaBuscadorComponent } from './facturacion-tienda/buscador/tienda-venta-buscador.component';
import { TiendaVentaProductoComponent } from './facturacion-tienda/productos/tienda-venta-producto.component';
import { TiendaVentaPaquetesComponent } from './facturacion-tienda/paquetes/tienda-venta-paquetes.component';
import { TiendaVentaCitasComponent } from './facturacion-tienda/citas/tienda-venta-citas.component';
import { VentaDetallesComponent } from './facturacion-tienda/detalles/venta-detalles.component';
import { MetodosDePagoComponent } from './facturacion-tienda/metodos-de-pago/metodos-de-pago.component';
import { CotizacionFormComponent } from './facturacion-tienda/cotizacion-form/cotizacion-form.component';
import { ProductoSpecsComponent } from './facturacion-tienda/detalles/producto-specs.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    NgSelectModule,
    ReactiveFormsModule,
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    ModalModule.forRoot(),
    FocusModule.forRoot()
  ],
  declarations: [
    FacturacionComponent,
    FacturacionConsignaComponent,
    TiendaVentaBuscadorComponent,
    TiendaVentaProductoComponent,
    TiendaVentaPaquetesComponent,
    TiendaVentaCitasComponent,
    VentaDetallesComponent,
    MetodosDePagoComponent,
    CotizacionFormComponent,
    ProductoSpecsComponent
  ],
  exports: [
    FacturacionComponent,
    FacturacionConsignaComponent,
    TiendaVentaBuscadorComponent,
    TiendaVentaProductoComponent,
    TiendaVentaPaquetesComponent,
    TiendaVentaCitasComponent,
    VentaDetallesComponent,
    MetodosDePagoComponent,
    ProductoSpecsComponent
  ]
})
export class FacturacionModule { }
