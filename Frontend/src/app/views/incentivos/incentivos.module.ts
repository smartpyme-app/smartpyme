import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ModalModule } from 'ngx-bootstrap/modal';
import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';

import { IncentivosRoutingModule } from './incentivos-routing.module';
import { DashboardComponent } from './dashboard/dashboard.component';
import { PlanillaNavComponent } from '@views/planillas/planilla-nav/planilla-nav.component';

@NgModule({
  declarations: [DashboardComponent],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    IncentivosRoutingModule,
    SharedModule,
    PipesModule,
    ModalModule.forRoot(),
    PlanillaNavComponent
  ]
})
export class IncentivosModule {}
