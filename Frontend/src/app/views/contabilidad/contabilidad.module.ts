import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { PipesModule } from '@pipes/pipes.module';
import { SharedModule } from '@shared/shared.module';
import { NgChartsModule } from 'ng2-charts';
import { NgSelectModule } from '@ng-select/ng-select';

import { ContabilidadRoutingModule } from './contabilidad.routing.module';
import { PresupuestosComponent } from './presupuestos/presupuestos.component';
import { PresupuestoComponent } from './presupuestos/presupuesto/presupuesto.component';
import { PresupuestoDetallesComponent } from './presupuestos/presupuesto-detalles/presupuesto-detalles.component';
import { ConsumidorFinalComponent } from '@views/contabilidad/libro-iva/consumidor-final/consumidor-final.component';
import { ContribuyentesComponent } from '@views/contabilidad/libro-iva/contribuyentes/contribuyentes.component';
import { LibroComprasComponent } from '@views/contabilidad/libro-compras/libro-compras.component';
import { LibroAnuladosComponent } from '@views/contabilidad/libro-anulados/libro-anulados.component';
import { LibroComprasSujetosExcluidosComponent } from '@views/contabilidad/libro-compras-sujetos-excluidos/libro-compras-sujetos-excluidos.component';

import { CuentasComponent } from '@views/contabilidad/bancos/cuentas/cuentas.component';
import { CuentaComponent } from '@views/contabilidad/bancos/cuentas/cuenta/cuenta.component';
import { ChequesComponent } from '@views/contabilidad/bancos/cheques/cheques.component';
import { ChequeComponent } from '@views/contabilidad/bancos/cheques/cheque/cheque.component';
import { TransaccionesComponent } from '@views/contabilidad/bancos/transacciones/transacciones.component';
import { TransaccionComponent } from '@views/contabilidad/bancos/transacciones/transaccion/transaccion.component';
import { ConciliacionesComponent } from '@views/contabilidad/bancos/conciliaciones/conciliaciones.component';
import { ConciliacionComponent } from '@views/contabilidad/bancos/conciliaciones/conciliacion/conciliacion.component';
import { CatalogoCuentasComponent } from '@views/contabilidad/catalogo-cuentas/catalogo-cuentas.component';
import { CatalogoCuentaComponent } from '@views/contabilidad/catalogo-cuentas/catalogo-cuenta/catalogo-cuenta.component';
import { PartidasComponent } from '@views/contabilidad/partidas/partidas.component';
import { PartidaComponent } from '@views/contabilidad/partidas/partida/partida.component';
import { PartidaDetallesComponent } from '@views/contabilidad/partidas/partida/detalles/partida-detalles.component';
import { ParditasDatosComponent } from './partidas/datos-partidas/datos-partida.component';
import { ContabilidadConfiguracionComponent } from '@views/contabilidad/configuracion/contabilidad-configuracion.component';
import { CierreMesComponent } from '@views/contabilidad/cierre-mes/cierre-mes.component';

import { ComprasModule } from '@views/compras/compras.module';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    NgChartsModule,
    NgSelectModule,
    ComprasModule,
    ContabilidadRoutingModule,
    PopoverModule.forRoot(),
    FocusModule.forRoot(),
    TooltipModule.forRoot(),
    TabsModule.forRoot(),
  ],
  declarations: [
    PresupuestosComponent,
    PresupuestoComponent,
    PresupuestoDetallesComponent,
    ConsumidorFinalComponent,
    ContribuyentesComponent,
    LibroComprasComponent,
    LibroAnuladosComponent,
    LibroComprasSujetosExcluidosComponent,
    CuentasComponent,
    CuentaComponent,
    ChequesComponent,
    ChequeComponent,
    TransaccionesComponent,
    TransaccionComponent,
    CatalogoCuentasComponent,
    CatalogoCuentaComponent,
    PartidasComponent,
    PartidaComponent,
    PartidaDetallesComponent,
    ConciliacionesComponent,
    ConciliacionComponent,
    ParditasDatosComponent,
    ContabilidadConfiguracionComponent,
    CierreMesComponent,

  ],
  exports: [
    PresupuestosComponent,
    PresupuestoComponent,
    PresupuestoDetallesComponent,
    ConsumidorFinalComponent,
    ContribuyentesComponent,
    LibroComprasComponent,
    LibroAnuladosComponent,
    LibroComprasSujetosExcluidosComponent,
    CuentasComponent,
    CuentaComponent,
    ChequesComponent,
    ChequeComponent,
    TransaccionesComponent,
    TransaccionComponent,
    CatalogoCuentasComponent,
    CatalogoCuentaComponent,
    PartidasComponent,
    PartidaComponent,
    PartidaDetallesComponent,
    ConciliacionesComponent,
    ConciliacionComponent,
    ParditasDatosComponent,
    ContabilidadConfiguracionComponent,
    CierreMesComponent,
  ]
})
export class ContabilidadModule { }
