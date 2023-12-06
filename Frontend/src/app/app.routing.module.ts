import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';

import { AuthGuard } from './guards/auth.guard';
import { AdminGuard } from './guards/admin.guard';

import { NotFoundComponent }    from './shared/404/not-found.component';
import { LoginComponent }    from './auth/login/login.component';
import { RegisterComponent }    from './auth/register/register.component';
import { PagoComponent }    from './auth/register/pago/pago.component';
import { LockComponent }    from './auth/lock/lock.component';
import { ForgetComponent }    from './auth/forget/forget.component';
import { QuicklinkStrategy } from 'ngx-quicklink';

const routes: Routes = [

    { path: 'login',    component: LoginComponent, title: 'Inicio de sesión' },
    { path: 'registro',    component: RegisterComponent, title: 'Registro' },
    { path: 'pago',    component: PagoComponent, title: 'Pago' },
    { path: 'restablecer-cuenta',    component: ForgetComponent, title: 'Restablecer contraseña' },
    { path: 'lock',     component: LockComponent },

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

    // Contabilidad
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/contabilidad/contabilidad.module').then(m => m.ContabilidadModule),
    },
    // Citas
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/citas/citas.module').then(m => m.CitasModule),
    },
    // Admin
    {
      path: '', canActivate: [AuthGuard],
      loadChildren: () => import('./views/admin/admin.module').then(m => m.AdminModule),
    },

    // Not Found
    {
      path: '**',
      component: NotFoundComponent
    }
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})

export class AppRoutingModule { }
