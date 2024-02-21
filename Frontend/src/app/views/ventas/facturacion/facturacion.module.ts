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
import { TiendaVentaProductoComponent } from './facturacion-tienda/productos/tienda-venta-producto.component';
import { VentaDetallesComponent } from './facturacion-tienda/detalles/venta-detalles.component';

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
    TiendaVentaProductoComponent,
    VentaDetallesComponent,
  ],
  exports: [
    FacturacionComponent,
    FacturacionConsignaComponent,
    TiendaVentaProductoComponent,
    VentaDetallesComponent,
  ]
})
export class FacturacionModule { }
