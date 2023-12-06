import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '../../guards/auth.guard';
import { LayoutComponent } from '../../layout/layout.component';

import { DashComponent }     from '../../views/dash/dash.component';
import { CajaVentasComponent }     from '../../views/dash/caja/ventas/caja-ventas.component';
import { CajaDevolucionesComponent }     from '../../views/dash/caja/devoluciones/caja-devoluciones.component';
import { VendedorProductosComponent }     from '../../views/dash/vendedor/productos/vendedor-productos.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Dashboard',
    children: [
        { path: '', component: DashComponent },
        { path: 'dash/caja/ventas', component: CajaVentasComponent },
        { path: 'dash/caja/devoluciones/ventas', component: CajaDevolucionesComponent },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DashRoutingModule { }
