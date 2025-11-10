import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Dashboard',
    children: [
        { 
          path: '', 
          loadComponent: () => import('../../views/dash/dash.component').then(m => m.DashComponent) 
        },
        { 
          path: 'vendedor/ventas', 
          loadComponent: () => import('../../views/dash/caja/ventas/caja-ventas.component').then(m => m.CajaVentasComponent) 
        },
        { 
          path: 'vendedor/productos', 
          loadComponent: () => import('../../views/dash/vendedor/productos/vendedor-productos.component').then(m => m.VendedorProductosComponent) 
        },
        { 
          path: 'vendedor/devoluciones/ventas', 
          loadComponent: () => import('../../views/dash/caja/devoluciones/caja-devoluciones.component').then(m => m.CajaDevolucionesComponent) 
        },
        { 
          path: 'cierre-de-caja', 
          loadComponent: () => import('@views/reportes/corte/corte.component').then(m => m.CorteComponent), 
          title: 'Cierre de caja'
        },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DashRoutingModule { }
