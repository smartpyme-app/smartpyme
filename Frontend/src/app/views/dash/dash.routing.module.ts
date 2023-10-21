import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '../../guards/auth.guard';
import { LayoutComponent } from '../../layout/layout.component';

import { DashComponent }     from '../../views/dash/dash.component';
import { MeseroDashComponent }     from '../../views/dash/mesero/mesero-dash.component';
import { CajaVentasComponent }     from '../../views/dash/caja/ventas/caja-ventas.component';
import { CajaDevolucionesComponent }     from '../../views/dash/caja/devoluciones/caja-devoluciones.component';
import { CocineroDashComponent }     from '../../views/dash/cocinero/cocinero-dash.component';
import { CocinaGeneralComponent }     from '../../views/dash/cocinero/general/cocina-general.component';
import { CocinaDepartamentoComponent }     from '../../views/dash/cocinero/departamento/cocina-departamento.component';

import { VendedorProductosComponent }     from '../../views/dash/vendedor/productos/vendedor-productos.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Dashboard',
    children: [
        { path: '', component: DashComponent },
        { path: 'dash/meseros', component: MeseroDashComponent },
        { path: 'dash/cocina', component: CocineroDashComponent },
        { path: 'dash/caja/ventas', component: CajaVentasComponent },
        { path: 'dash/caja/devoluciones/ventas', component: CajaDevolucionesComponent },
        { path: 'dash/cocina/general', component: CocinaGeneralComponent },
        { path: 'dash/cocina/departamento/:id', component: CocinaDepartamentoComponent },
        { path: 'dash/vendedor/productos', component: VendedorProductosComponent },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DashRoutingModule { }
