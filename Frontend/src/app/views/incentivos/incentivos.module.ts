import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';

import { IncentivosRoutingModule } from './incentivos-routing.module';
import { DashboardComponent } from './dashboard/dashboard.component';

@NgModule({
  declarations: [DashboardComponent],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    IncentivosRoutingModule,
    SharedModule,
    PipesModule
  ]
})
export class IncentivosModule {}
