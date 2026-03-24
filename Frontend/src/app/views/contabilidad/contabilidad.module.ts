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
import { LibroAnuladosComponent } from '@views/contabilidad/libro-anulados/libro-anulados.component';
import { LibroComprasSujetosExcluidosComponent } from '@views/contabilidad/libro-compras-sujetos-excluidos/libro-compras-sujetos-excluidos.component';
import { CuentasComponent } from '@views/contabilidad/bancos/cuentas/cuentas.component';
import { CuentaComponent } from '@views/contabilidad/bancos/cuentas/cuenta/cuenta.component';

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
    LibroAnuladosComponent,
    LibroComprasSujetosExcluidosComponent,
    CuentasComponent,
    CuentaComponent
  ],
  exports: [
    PresupuestosComponent,
    PresupuestoComponent,
    PresupuestoDetallesComponent,
    ConsumidorFinalComponent,
    ContribuyentesComponent,
    LibroComprasComponent,
    LibroAnuladosComponent,
    LibroComprasSujetosExcluidosComponent
  ]
})
export class ContabilidadModule { }
