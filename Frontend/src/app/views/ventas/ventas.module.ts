import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PipesModule } from '@pipes/pipes.module';
import { ModalModule } from 'ngx-bootstrap/modal';
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
import { SolicitudesCompraComponent } from '@views/ventas/solicitudes-compra/solicitudes-compra.component';

import { CanalesComponent } from '@views/ventas/canales/canales.component';
import { FormasDePagoComponent } from '@views/ventas/formas-de-pago/formas-de-pago.component';
import { BancosComponent } from '@views/ventas/bancos/bancos.component';
import { ImpuestosComponent } from '@views/ventas/impuestos/impuestos.component';
import { RetencionesComponent } from '@views/ventas/retenciones/retenciones.component';
import { DocumentosComponent } from '@views/ventas/documentos/documentos.component';

import { DevolucionesVentasComponent } from '@views/ventas/devoluciones/devoluciones-ventas.component';
import { DevolucionVentaNuevaComponent } from '@views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component';
// DevolucionVentaDetallesComponent es importado por DevolucionVentaNuevaComponent, no necesita estar aquí
import { DevolucionVentaComponent } from '@views/ventas/devoluciones/devolucion/devolucion-venta.component';

import { OrdenesProduccionComponent } from '@views/ventas/orden_produccion/ordenes-produccion.component';
import { CrearOrdenProduccionComponent } from '@views/ventas/orden_produccion/crear_orden/crear-orden-produccion.component';
import { DocumentoHistorialComponent } from '@views/ventas/documentos/historial/documento-historial.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    VentasRoutingModule,
    NgSelectModule,
    PopoverModule.forRoot(),
    ModalModule.forRoot(),
    TooltipModule.forRoot(),
    ProgressbarModule.forRoot(),
    // Componentes standalone
    VentasComponent,
    VentaComponent,
    RecurrentesComponent,
    AbonosVentasComponent,
    CotizacionesComponent,
    SolicitudesCompraComponent,
    CanalesComponent,
    FormasDePagoComponent,
    BancosComponent,
    ImpuestosComponent,
    RetencionesComponent,
    DocumentosComponent,
    DevolucionesVentasComponent,
    DevolucionVentaComponent,
    DevolucionVentaNuevaComponent,
    // DevolucionVentaDetallesComponent es importado por DevolucionVentaNuevaComponent
    OrdenesProduccionComponent,
    CrearOrdenProduccionComponent,
    DocumentoHistorialComponent
  ],
  exports: [
    // Componentes standalone exportados (ya están importados arriba)
    VentasComponent,
    VentaComponent,
    RecurrentesComponent,
    AbonosVentasComponent,
    CotizacionesComponent,
    SolicitudesCompraComponent,
    CanalesComponent,
    FormasDePagoComponent,
    BancosComponent,
    ImpuestosComponent,
    RetencionesComponent,
    DocumentosComponent,
    DevolucionesVentasComponent,
    DevolucionVentaComponent,
    DevolucionVentaNuevaComponent,
    // DevolucionVentaDetallesComponent es importado por DevolucionVentaNuevaComponent
    OrdenesProduccionComponent,
    CrearOrdenProduccionComponent
  ]
})
export class VentasModule { }

