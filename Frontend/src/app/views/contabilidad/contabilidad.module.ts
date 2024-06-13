import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
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

import { CuentasComponent } from '@views/contabilidad/bancos/cuentas/cuentas.component';
import { CuentaComponent } from '@views/contabilidad/bancos/cuentas/cuenta/cuenta.component';
import { ChequesComponent } from '@views/contabilidad/bancos/cheques/cheques.component';
import { ChequeComponent } from '@views/contabilidad/bancos/cheques/cheque/cheque.component';
import { TransaccionesComponent } from '@views/contabilidad/bancos/transacciones/transacciones.component';
import { TransaccionComponent } from '@views/contabilidad/bancos/transacciones/transaccion/transaccion.component';

import { CatalogoCuentasComponent } from '@views/contabilidad/catalogo-cuentas/catalogo-cuentas.component';
import { CatalogoCuentaComponent } from '@views/contabilidad/catalogo-cuentas/catalogo-cuenta/catalogo-cuenta.component';
import { PartidasComponent } from './partidas/partidas.component';
import { PartidaComponent } from './partidas/partida/partida.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    SharedModule,
    NgChartsModule,
    NgSelectModule,
    ContabilidadRoutingModule,
    PopoverModule.forRoot(),
    FocusModule.forRoot(),
    TooltipModule.forRoot()
  ],
  declarations: [
    PresupuestosComponent,
    PresupuestoComponent,
    PresupuestoDetallesComponent,
    ConsumidorFinalComponent,
    ContribuyentesComponent,
    LibroComprasComponent,
    CuentasComponent,
    CuentaComponent,
    ChequesComponent,
    ChequeComponent,
    TransaccionesComponent,
    TransaccionComponent,
    CatalogoCuentasComponent,
    CatalogoCuentaComponent,
    PartidasComponent,
    PartidaComponent
  ],
  exports: [
    PresupuestosComponent,
    PresupuestoComponent,
    PresupuestoDetallesComponent,
    ConsumidorFinalComponent,
    ContribuyentesComponent,
    LibroComprasComponent,
    CuentasComponent,
    CuentaComponent,
    ChequesComponent,
    ChequeComponent,
    TransaccionesComponent,
    TransaccionComponent,
    CatalogoCuentasComponent,
    CatalogoCuentaComponent
  ]
})
export class ContabilidadModule { }
