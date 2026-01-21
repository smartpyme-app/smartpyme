import { NgModule, CUSTOM_ELEMENTS_SCHEMA } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgxEchartsModule } from 'ngx-echarts';
import * as echarts from 'echarts';
import { RevoGrid } from '@revolist/angular-datagrid';
import { AgGridModule } from 'ag-grid-angular';

import { DashboardComponent } from './dashboard.component';
import { ChartCardComponent } from './components/chart-card/chart-card.component';
import { LineChartComponent } from './components/line-chart/line-chart.component';
import { BarChartComponent } from './components/bar-chart/bar-chart.component';
import { PieChartComponent } from './components/pie-chart/pie-chart.component';
import { TreemapChartComponent } from './components/treemap-chart/treemap-chart.component';
import { AccountsListComponent } from './components/accounts-list/accounts-list.component';
import { CashFlowGaugeComponent } from './components/cash-flow-gauge/cash-flow-gauge.component';
import { BudgetCardComponent } from './components/budget-card/budget-card.component';

// Componentes de secciones
import { ResultadosComponent } from './sections/resultados/resultados.component';
import { VentasComponent } from './sections/ventas/ventas.component';
import { FinanzasComponent } from './sections/finanzas/finanzas.component';
import { GastosComponent } from './sections/gastos/gastos.component';
import { ControlCuentasComponent } from './sections/control-cuentas/control-cuentas.component';
import { InventarioComponent } from './sections/inventario/inventario.component';

import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    NgxEchartsModule.forRoot({ echarts }),
    RevoGrid,
    AgGridModule,
    SharedModule,
    PipesModule
  ],
  declarations: [
    DashboardComponent,
    ChartCardComponent,
    LineChartComponent,
    BarChartComponent,
    PieChartComponent,
    TreemapChartComponent,
    AccountsListComponent,
    CashFlowGaugeComponent,
    BudgetCardComponent,
    ResultadosComponent,
    VentasComponent,
    FinanzasComponent,
    GastosComponent,
    ControlCuentasComponent,
    InventarioComponent
  ],
  exports: [
    DashboardComponent,
    ChartCardComponent,
    LineChartComponent,
    BarChartComponent,
    PieChartComponent,
    TreemapChartComponent,
    AccountsListComponent,
    CashFlowGaugeComponent,
    BudgetCardComponent,
    RevoGrid
  ],
  schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class DashboardModule { }

