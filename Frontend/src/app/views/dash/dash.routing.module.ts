import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '../../guards/auth.guard';
import { LayoutComponent } from '../../layout/layout.component';

import { DashComponent }     from '../../views/dash/dash.component';
import { CajaVentasComponent }     from '../../views/dash/caja/ventas/caja-ventas.component';
import { CajaDevolucionesComponent }     from '../../views/dash/caja/devoluciones/caja-devoluciones.component';
import { VendedorProductosComponent }     from '../../views/dash/vendedor/productos/vendedor-productos.component';

import { CorteComponent }    from '@views/reportes/corte/corte.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Dashboard',
    children: [
        { path: '', component: DashComponent },
        { path: 'vendedor/ventas', component: CajaVentasComponent },
        { path: 'vendedor/productos', component: VendedorProductosComponent },
        { path: 'vendedor/devoluciones/ventas', component: CajaDevolucionesComponent },
        { path: 'cierre-de-caja', component: CorteComponent, title: 'Cierre de caja'},
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DashRoutingModule { }
