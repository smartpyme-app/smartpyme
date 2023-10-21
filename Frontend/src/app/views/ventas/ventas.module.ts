import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PipesModule } from '../../pipes/pipes.module';
import { ModalModule } from 'ngx-bootstrap/modal';
import { FocusModule } from 'angular2-focus';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { SharedModule } from '../../shared/shared.module';

import { VentasRoutingModule } from './ventas.routing.module';

import { CreditosModule } from '../creditos/creditos.module';

import { VentasComponent } from './ventas.component';
import { VentaComponent } from './venta/venta.component';
import { DevolucionesVentasComponent } from './devoluciones/devoluciones-ventas.component';
import { DevolucionVentaNuevaComponent } from '../../views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component';
import { DevolucionVentaDetallesComponent } from '../../views/ventas/devoluciones/devolucion-nueva/detalles/devolucion-venta-detalles.component';
import { DevolucionVentaComponent } from './devoluciones/devolucion/devolucion-venta.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    CreditosModule,
    VentasRoutingModule,
    PopoverModule.forRoot(),
    FocusModule.forRoot(),
    ModalModule.forRoot(),
    TooltipModule.forRoot(),
    ProgressbarModule.forRoot(),
  ],
  declarations: [
    VentasComponent,
    VentaComponent,
    DevolucionesVentasComponent,
    DevolucionVentaComponent,
    DevolucionVentaNuevaComponent,
    DevolucionVentaDetallesComponent
  ],
  exports: [
    VentasComponent,
    VentaComponent,
    DevolucionesVentasComponent,
    DevolucionVentaComponent,
    DevolucionVentaNuevaComponent,
    DevolucionVentaDetallesComponent
  ]
})
export class VentasModule { }
