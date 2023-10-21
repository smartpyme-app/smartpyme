import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';

import { PipesModule } from '../../pipes/pipes.module';
import { FocusModule } from 'angular2-focus';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

import { CreditosRoutingModule } from './creditos.routing.module';

import { SharedModule } from '../../shared/shared.module';
import { CreditosComponent } from './creditos.component';
import { PagosComponent } from './pagos/pagos.component';
import { CreditoComponent } from './credito/credito.component';
import { FormCreditoComponent } from './form/form-credito.component';
import { CreditoPagosComponent } from './credito/pagos/credito-pagos.component';
import { PlanDePagosComponent } from './plan-de-pagos/plan-de-pagos.component';

export class AppModule {}

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    SharedModule,
    PipesModule,
    CreditosRoutingModule,
    FocusModule.forRoot(),
    ProgressbarModule.forRoot(),
    TooltipModule.forRoot(),
  ],
  declarations: [
    CreditosComponent,
    CreditoComponent,
    FormCreditoComponent,
    CreditoPagosComponent,
    PagosComponent,
    PlanDePagosComponent,
  ],
  exports: [
    CreditosComponent,
    CreditoComponent,
    FormCreditoComponent,
    CreditoPagosComponent,
    PagosComponent,
    PlanDePagosComponent,
  ]
})
export class CreditosModule { }
