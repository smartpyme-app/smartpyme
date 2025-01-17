import { NgModule } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { TourNgxBootstrapModule } from 'ngx-ui-tour-ngx-bootstrap';
import { CollapseModule } from 'ngx-bootstrap/collapse';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { FocusModule } from 'angular2-focus';
import { BsDropdownModule } from 'ngx-bootstrap/dropdown';
import { NgChartsModule } from 'ng2-charts';
import { PipesModule } from '@pipes/pipes.module';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaywallRoutingModule } from './paywall.routing.module';

import { PaywallComponent }         from './paywall.component';
@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    NgChartsModule,
    PipesModule,
    SharedModule,
    PaywallRoutingModule,
    NgSelectModule,
    TourNgxBootstrapModule,
    PopoverModule.forRoot(),
    TooltipModule.forRoot(),
    FocusModule.forRoot(),
    BsDropdownModule.forRoot(),
    TabsModule.forRoot(),
    CollapseModule.forRoot(),
    ProgressbarModule.forRoot(),
  ],
  declarations: [
    PaywallComponent
  ],
  ],
  exports: [
    PaywallComponent
  ]
})
export class PaywallModule { }
