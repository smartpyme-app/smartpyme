import { NgModule } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { CollapseModule } from 'ngx-bootstrap/collapse';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { TabsModule } from 'ngx-bootstrap/tabs';
import { BsDropdownModule } from 'ngx-bootstrap/dropdown';
import { NgChartsModule } from 'ng2-charts';
import { PipesModule } from '@pipes/pipes.module';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { SharedModule } from '@shared/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaywallRoutingModule } from './paywall-layout.routing.module';

// Los componentes standalone se cargan de forma lazy en el routing
// No necesitan ser importados aquí

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
    PopoverModule.forRoot(),
    TooltipModule.forRoot(),
    BsDropdownModule.forRoot(),
    TabsModule.forRoot(),
    CollapseModule.forRoot(),
    ProgressbarModule.forRoot(),
  ],
  declarations: [],
  exports: []
})
export class PaywallModule { }
