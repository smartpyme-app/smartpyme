import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PipesModule } from '@pipes/pipes.module';
import { ModalModule } from 'ngx-bootstrap/modal';
import { FocusModule } from 'angular2-focus';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';

import { VentasRoutingModule } from '@views/ventas/ventas.routing.module';
import { VentasComponent } from '@views/ventas/ventas.component';
import { VentaComponent } from '@views/ventas/venta/venta.component';

import { CotizacionesComponent } from '@views/ventas/cotizaciones/cotizaciones.component';

import { CanalesComponent } from '@views/ventas/canales/canales.component';
import { DocumentosComponent } from '@views/ventas/documentos/documentos.component';

import { DevolucionesVentasComponent } from '@views/ventas/devoluciones/devoluciones-ventas.component';
import { DevolucionVentaNuevaComponent } from '@views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component';
import { DevolucionVentaDetallesComponent } from '@views/ventas/devoluciones/devolucion-nueva/detalles/devolucion-venta-detalles.component';
import { DevolucionVentaComponent } from '@views/ventas/devoluciones/devolucion/devolucion-venta.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    VentasRoutingModule,
    NgSelectModule,
    PopoverModule.forRoot(),
    FocusModule.forRoot(),
    ModalModule.forRoot(),
    TooltipModule.forRoot(),
    ProgressbarModule.forRoot(),
  ],
  declarations: [
    VentasComponent,
    VentaComponent,
    CotizacionesComponent,
    CanalesComponent,
    DocumentosComponent,
    DevolucionesVentasComponent,
    DevolucionVentaComponent,
    DevolucionVentaNuevaComponent,
    DevolucionVentaDetallesComponent
  ],
  exports: [
    VentasComponent,
    VentaComponent,
    CotizacionesComponent,
    CanalesComponent,
    DocumentosComponent,
    DevolucionesVentasComponent,
    DevolucionVentaComponent,
    DevolucionVentaNuevaComponent,
    DevolucionVentaDetallesComponent
  ]
})
export class VentasModule { }
