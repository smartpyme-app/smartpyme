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

import { RecurrentesComponent } from '@views/ventas/recurrentes/recurrentes.component';
import { AbonosVentasComponent } from '@views/ventas/abonos/abonos-ventas.component';

import { CotizacionesComponent } from '@views/ventas/cotizaciones/cotizaciones.component';

import { CanalesComponent } from '@views/ventas/canales/canales.component';
import { FormasDePagoComponent } from '@views/ventas/formas-de-pago/formas-de-pago.component';
import { BancosComponent } from '@views/ventas/bancos/bancos.component';
import { ImpuestosComponent } from '@views/ventas/impuestos/impuestos.component';
import { RetencionesComponent } from '@views/ventas/retenciones/retenciones.component';
import { DocumentosComponent } from '@views/ventas/documentos/documentos.component';

import { DevolucionesVentasComponent } from '@views/ventas/devoluciones/devoluciones-ventas.component';
import { DevolucionVentaNuevaComponent } from '@views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component';
import { DevolucionVentaDetallesComponent } from '@views/ventas/devoluciones/devolucion-nueva/detalles/devolucion-venta-detalles.component';
import { DevolucionVentaComponent } from '@views/ventas/devoluciones/devolucion/devolucion-venta.component';

import { OrdenesProduccionComponent } from '@views/ventas/orden_produccion/ordenes-produccion.component';
import { CrearOrdenProduccionComponent } from '@views/ventas/orden_produccion/crear_orden/crear-orden-produccion.component';
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
    RecurrentesComponent,
    AbonosVentasComponent,
    CotizacionesComponent,
    CanalesComponent,
    FormasDePagoComponent,
    BancosComponent,
    ImpuestosComponent,
    RetencionesComponent,
    DocumentosComponent,
    DevolucionesVentasComponent,
    DevolucionVentaComponent,
    DevolucionVentaNuevaComponent,
    DevolucionVentaDetallesComponent,
    OrdenesProduccionComponent,
    CrearOrdenProduccionComponent
  ],
  exports: [
    VentasComponent,
    VentaComponent,
    RecurrentesComponent,
    AbonosVentasComponent,
    CotizacionesComponent,
    CanalesComponent,
    FormasDePagoComponent,
    BancosComponent,
    ImpuestosComponent,
    RetencionesComponent,
    DocumentosComponent,
    DevolucionesVentasComponent,
    DevolucionVentaComponent,
    DevolucionVentaNuevaComponent,
    DevolucionVentaDetallesComponent,
    OrdenesProduccionComponent,
    CrearOrdenProduccionComponent
  ]
})
export class VentasModule { }

