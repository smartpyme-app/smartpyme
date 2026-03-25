import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';

import { AuthGuard } from './guards/auth.guard';
import { AdminGuard } from './guards/admin.guard';
import { SuperAdminGuard } from './guards/super-admin.guard';
import { SubscriptionGuard } from './guards/SuscriptionGuard.guard';

import { NotFoundComponent }    from './shared/404/not-found.component';
import { LoginComponent }    from './auth/login/login.component';
import { RegisterComponent }    from './auth/register/register.component';
import { PagoComponent }    from './auth/register/pago/pago.component';
import { PaymentSuccessComponent }    from './auth/register/pago/payment-success.component';
import { PaymentSuccessPaywallComponent }    from './layout/paywall/components/payment-success/payment-success.component';
import { LockComponent }    from './auth/lock/lock.component';
import { ForgetComponent }    from './auth/forget/forget.component';
import { QuicklinkStrategy } from 'ngx-quicklink';
import { SupervisorLimitadoGuard } from './guards/supervisor-limitado.guard';


const routes: Routes = [
    { path: 'login', component: LoginComponent, title: 'Inicio de sesión' },
    { path: 'registro', component: RegisterComponent, title: 'Registro' },
    { path: 'pago', component: PagoComponent, title: 'Pago' },
    { path: 'pago-exitoso', component: PaymentSuccessComponent, title: 'Pago exitoso' },
    { path: 'pago-exitoso-paywall', component: PaymentSuccessPaywallComponent, title: 'Pago exitoso' },
    { path: 'restablecer-cuenta', component: ForgetComponent, title: 'Restablecer contraseña' },
    { path: 'lock', component: LockComponent },

    // Paywall (sin SubscriptionGuard para permitir acceso)
    {
      path: 'paywall',
      canActivate: [AuthGuard],
      loadChildren: () => import('./layout/paywall/paywall-layout.module').then(m => m.PaywallModule),
    },

    // Rutas protegidas que requieren suscripción
    {
      path: '',
      canActivate: [AuthGuard, SubscriptionGuard],
      children: [
        // Dash
        {
          path: '',
          loadChildren: () => import('./views/dash/dash.module').then(m => m.DashModule),
        },
        // Ventas
        {
          path: '',
          loadChildren: () => import('./views/ventas/ventas.module').then(m => m.VentasModule),
        },
        // Inventario
        {
          path: '',
          loadChildren: () => import('./views/inventario/inventario.module').then(m => m.InventarioModule),
        },
        // Paquetes
        {
          path: '',
          loadChildren: () => import('./views/paquetes/paquetes.module').then(m => m.PaquetesModule),
        },
        // Planilla
        {
          path: '',
          loadChildren: () => import('./views/planillas/planillas.module').then(m => m.PlanillasModule),
        },
        // Proyectos
        {
          path: '',
          loadChildren: () => import('./views/proyectos/proyectos.module').then(m => m.ProyectosModule),
        },
        // Compras
        {
          path: '',
          canActivate: [AdminGuard,SupervisorLimitadoGuard],
          loadChildren: () => import('./views/compras/compras.module').then(m => m.ComprasModule),
        },
        // Contabilidad
        {
          path: '',
          canActivate: [AdminGuard],
          loadChildren: () => import('./views/contabilidad/contabilidad.module').then(m => m.ContabilidadModule),
        },
        // Citas
        {
          path: '',
          loadChildren: () => import('./views/citas/citas.module').then(m => m.CitasModule),
        },
        // Planilla
        {
          path: '',
          loadChildren: () => import('./views/planillas/planillas.module').then(m => m.PlanillasModule),
        },
        // Admin
        {
          path: '',
          canActivate: [AdminGuard],
          loadChildren: () => import('./views/admin/admin.module').then(m => m.AdminModule),
        },
        // Super Admin
        {
          path: '',
          canActivate: [SuperAdminGuard],
          loadChildren: () => import('./views/super-admin/super-admin.module').then(m => m.SuperAdminModule),
        },
        // Organizaciones Admin
        {
          path: '',
          loadChildren: () => import('./views/organizaciones-admin/organizaciones-admin.module').then(m => m.OrganizacionesAdminModule),
        },
        // Fidelización
        {
          path: '',
          loadChildren: () => import('./views/fidelizacion/fidelizacion.module').then(m => m.FidelizacionModule),
        }
      ]
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
