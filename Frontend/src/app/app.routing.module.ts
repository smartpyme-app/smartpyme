import { NgModule } from '@angular/core';
import { Routes, RouterModule, NoPreloading } from '@angular/router';

import { AuthGuard } from './guards/auth.guard';
import { SubscriptionGuard } from './guards/SuscriptionGuard.guard';

export const GUARD_TYPES = {
  ADMIN: 'admin',
  CITAS: 'citas',
  SUPER_ADMIN: 'superAdmin',
} as const;

export const routes: Routes = [
    { 
      path: 'login', 
      loadComponent: () => import('./auth/login/login.component').then(m => m.LoginComponent), 
      title: 'Inicio de sesión' 
    },
    { 
      path: 'registro', 
      loadComponent: () => import('./auth/register/register.component').then(m => m.RegisterComponent), 
      title: 'Registro' 
    },
    { 
      path: 'pago', 
      loadComponent: () => import('./auth/register/pago/pago.component').then(m => m.PagoComponent), 
      title: 'Pago' 
    },
    // pago-exitoso y pago-exitoso-paywall: módulos no existen en este branch, redirigir a pago
    { path: 'pago-exitoso', redirectTo: 'pago', pathMatch: 'full' },
    { path: 'pago-exitoso-paywall', redirectTo: 'pago', pathMatch: 'full' },
    { 
      path: 'restablecer-cuenta', 
      loadComponent: () => import('./auth/forget/forget.component').then(m => m.ForgetComponent), 
      title: 'Restablecer contraseña' 
    },
    { 
      path: 'lock', 
      loadComponent: () => import('./auth/lock/lock.component').then(m => m.LockComponent) 
    },

    // Paywall: módulo no existe en este branch, redirigir a login
    { path: 'paywall', redirectTo: 'login', pathMatch: 'full' },

    // Rutas protegidas que requieren suscripción
    {
      path: '',
      canActivate: [AuthGuard, SubscriptionGuard],
      children: [
        // Planilla (ruta explícita, antes de path '' para prioridad)
        {
          path: 'planilla',
          loadChildren: () => import('./views/planillas/planillas.module').then(m => m.PlanillasModule),
        },
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
        // Proyectos
        {
          path: '',
          loadChildren: () => import('./views/proyectos/proyectos.module').then(m => m.ProyectosModule),
        },
        // Compras
        {
          path: '',
          loadChildren: () => import('./views/compras/compras.module').then(m => m.ComprasModule),
        },
        // Contabilidad
        {
          path: '',
          loadChildren: () => import('./views/contabilidad/contabilidad.module').then(m => m.ContabilidadModule),
        },
        // Citas
        {
          path: '',
          loadChildren: () => import('./views/citas/citas.module').then(m => m.CitasModule),
        },
        // Admin
        {
          path: '',
          loadChildren: () => import('./views/admin/admin.module').then(m => m.AdminModule),
        },
        // Super Admin
        {
          path: '',
          loadChildren: () => import('./views/super-admin/super-admin.module').then(m => m.SuperAdminModule),
        },
        // Organizaciones Admin
        {
          path: '',
          loadChildren: () => import('./views/organizaciones-admin/organizaciones-admin.module').then(m => m.OrganizacionesAdminModule),
        }
      ]
    },

  // Not Found
  {
    path: '**',
    loadComponent: () => import('./shared/404/not-found.component').then(m => m.NotFoundComponent),
  },
];

@NgModule({
  imports: [RouterModule.forRoot(routes, {
    preloadingStrategy: NoPreloading
  })],
  exports: [RouterModule],
})
export class AppRoutingModule {}
