import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';

import { AuthGuard } from './guards/auth.guard';
import { AdminGuard } from './guards/admin.guard';

import { NotFoundComponent }    from './shared/404/not-found.component';
import { LoginComponent }    from './auth/login/login.component';
import { LockComponent }    from './auth/lock/lock.component';
import { AsistenciaComponent }     from './views/empleados/asistencias/asistencia/asistencia.component';

import { QuicklinkStrategy } from 'ngx-quicklink';

const routes: Routes = [

    { path: 'login',    component: LoginComponent, title: 'Inicio de sesión' },
    { path: 'lock',     component: LockComponent },
    { path: 'asistencia', component: AsistenciaComponent, title: 'Asistencia' },


    // Dash
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/dash/dash.module').then(m => m.DashModule),
    },
    // Ventas
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/ventas/ventas.module').then(m => m.VentasModule),
    },

    // Inventario
    {
      path: '', canActivate: [AuthGuard, AdminGuard],
      loadChildren: () => import('./views/inventario/inventario.module').then(m => m.InventarioModule),
    },

    // Compras
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/compras/compras.module').then(m => m.ComprasModule),
    },

    // Transporte
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/transporte/transporte.module').then(m => m.TransporteModule),
    },

    // Contabilidad
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/contabilidad/contabilidad.module').then(m => m.ContabilidadModule),
    },
    // Admin
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/admin/admin.module').then(m => m.AdminModule),
    },

    //Creditos 
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/creditos/creditos.module').then(m => m.CreditosModule),
    },

    //Empleados 
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/empleados/empleados.module').then(m => m.EmpleadosModule),
    },

    // Not Found
    {
      path: '**',
      component: NotFoundComponent
    }
];

@NgModule({
  imports: [RouterModule.forRoot(routes, {
    preloadingStrategy: QuicklinkStrategy
  })],
  exports: [RouterModule]
})

export class AppRoutingModule { }
