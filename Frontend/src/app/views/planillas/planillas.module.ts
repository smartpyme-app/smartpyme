import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PipesModule } from '@pipes/pipes.module';
import { ModalModule } from 'ngx-bootstrap/modal';
import { CollapseModule } from 'ngx-bootstrap/collapse';
import { FocusModule } from 'angular2-focus';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select'
import { NgxMaskDirective, NgxMaskPipe, provideNgxMask } from 'ngx-mask';

import { PlanillasComponent } from './planillas.component';
import { EmpleadosComponent } from './empleados/empleados.component';
import { AdministrarEmpleadoComponent } from './empleados/administrar-empleado.component';
import { PlanillasRoutingModule } from './planillas.routing.module';
import { PlanillaDetalleComponent } from './planillas/planilla-detalle.component';
import { BoletaPagoComponent } from './planillas/boleta-pago.component';
import { VerBoletasComponent } from './planillas/ver-boletas.component';
import { ConfiguracionPlanillaComponent } from '@views/planillas/configuracion-planilla/configuracion-planilla.component';
import { AguinaldosComponent } from './aguinaldos/aguinaldos.component';
import { AguinaldoDetalleComponent } from './aguinaldos/aguinaldo-detalle.component';
import { PrestamosComponent } from './prestamos/prestamos.component';
import { ReactiveFormsModule } from '@angular/forms';


import { PdfViewerModule } from 'ng2-pdf-viewer';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    PlanillasRoutingModule,
    NgSelectModule,
    NgxMaskDirective,
    NgxMaskPipe,
    PopoverModule.forRoot(),
    FocusModule.forRoot(),
    ModalModule.forRoot(),
    CollapseModule.forRoot(),
    TooltipModule.forRoot(),
    ProgressbarModule.forRoot(),
    PdfViewerModule,
    ReactiveFormsModule
  ],
  declarations: [
    PlanillasComponent,
    EmpleadosComponent,
    AdministrarEmpleadoComponent,
    PlanillaDetalleComponent,
    BoletaPagoComponent,
    VerBoletasComponent,
    ConfiguracionPlanillaComponent,
    AguinaldosComponent,
    AguinaldoDetalleComponent,
    PrestamosComponent
  ],
  exports: [
    PlanillasComponent,
    EmpleadosComponent,
    AdministrarEmpleadoComponent,
    PlanillaDetalleComponent,
    BoletaPagoComponent,
    VerBoletasComponent,
    ConfiguracionPlanillaComponent,
    AguinaldosComponent,
    AguinaldoDetalleComponent,
    PrestamosComponent
  ]
})
export class PlanillasModule { }