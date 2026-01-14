import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgxEchartsModule } from 'ngx-echarts';
import * as echarts from 'echarts';
import { RevoGrid } from '@revolist/angular-datagrid';

import { DashboardComponent } from './dashboard.component';
import { ChartCardComponent } from './components/chart-card/chart-card.component';
import { LineChartComponent } from './components/line-chart/line-chart.component';
import { BarChartComponent } from './components/bar-chart/bar-chart.component';
import { PieChartComponent } from './components/pie-chart/pie-chart.component';
import { AccountsListComponent } from './components/accounts-list/accounts-list.component';
import { CashFlowGaugeComponent } from './components/cash-flow-gauge/cash-flow-gauge.component';

import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';
import { BudgetCardComponent } from './components/budget-card/budget-card.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    NgxEchartsModule.forRoot({ echarts }),
    RevoGrid,
    SharedModule,
    PipesModule
  ],
  declarations: [
    DashboardComponent,
    ChartCardComponent,
    LineChartComponent,
    BarChartComponent,
    PieChartComponent,
    AccountsListComponent,
    CashFlowGaugeComponent,
    BudgetCardComponent
  ],
  exports: [
    DashboardComponent,
    ChartCardComponent,
    LineChartComponent,
    BarChartComponent,
    PieChartComponent,
    AccountsListComponent,
    CashFlowGaugeComponent,
    BudgetCardComponent
  ]
})
export class DashboardModule { }

