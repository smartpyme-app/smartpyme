import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { ConfigCategoriasComponent } from './config-categorias/config-categorias.component';
import { PeriodosLiquidacionesComponent } from './periodos-liquidaciones/periodos-liquidaciones.component';
import { PeriodoDetalleComponent } from './periodo-detalle/periodo-detalle.component';
import { ReportesComponent } from './reportes/reportes.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Comisiones',
    canActivate: [FuncionalidadGuard],
    data: { funcionalidadSlug: 'comisiones-vendedores' },
    children: [
      {
        path: '',
        redirectTo: 'comisiones/configuracion',
        pathMatch: 'full'
      },
      {
        path: 'comisiones/configuracion',
        component: ConfigCategoriasComponent,
        title: 'Configuración de comisiones'
      },
      {
        path: 'comisiones/periodos',
        component: PeriodosLiquidacionesComponent,
        title: 'Períodos y liquidaciones'
      },
      {
        path: 'comisiones/periodos/:id',
        component: PeriodoDetalleComponent,
        title: 'Detalle del período'
      },
      {
        path: 'comisiones/reportes',
        component: ReportesComponent,
        title: 'Reportes de comisiones'
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ComisionesRoutingModule {}
