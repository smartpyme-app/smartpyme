import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { PermissionGuard } from '@guards/permission.guard';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { SLUG_PRESTAMOS_EMPRESA } from './prestamos/prestamos-acceso';

const prestamosGuards = [PermissionGuard, FuncionalidadGuard];
const prestamosData = { permission: 'finanzas.prestamos.ver', funcionalidadSlug: SLUG_PRESTAMOS_EMPRESA };

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
      {
        path: 'prestamos',
        loadComponent: () => import('./prestamos/prestamos-list.component').then(m => m.PrestamosListComponent),
        canActivate: prestamosGuards,
        data: prestamosData,
        title: 'Préstamos'
      },
      {
        path: 'prestamos/nuevo',
        loadComponent: () => import('./prestamos/prestamo-form.component').then(m => m.PrestamoFormComponent),
        canActivate: prestamosGuards,
        data: { permission: 'finanzas.prestamos.crear', funcionalidadSlug: SLUG_PRESTAMOS_EMPRESA },
        title: 'Nuevo préstamo'
      },
      {
        path: 'prestamos/:id',
        loadComponent: () => import('./prestamos/prestamo-detalle.component').then(m => m.PrestamoDetalleComponent),
        canActivate: prestamosGuards,
        data: prestamosData,
        title: 'Préstamo'
      },
      {
        path: 'antiguedad-saldos',
        redirectTo: '/finanzas/reportes/antiguedad-cxc',
        pathMatch: 'full'
      },
      {
        path: 'reportes',
        pathMatch: 'full',
        loadComponent: () => import('@views/finanzas/reportes/finanzas-reportes-nav.component').then(m => m.FinanzasReportesRedirectComponent),
        title: 'Reportes'
      },
      {
        path: 'reportes/cuentas-cobrar',
        loadComponent: () => import('@views/ventas/clientes/cuentas-cobrar/cuentas-cobrar.component').then(m => m.CuentasCobrarComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'finanzas.reporteria.ver' },
        title: 'Cuentas por cobrar'
      },
      {
        path: 'reportes/cuentas-pagar',
        loadComponent: () => import('@views/compras/proveedores/cuentas-pagar/cuentas-pagar.component').then(m => m.CuentasPagarComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'finanzas.reporteria.ver' },
        title: 'Cuentas por pagar'
      },
      {
        path: 'reportes/antiguedad-saldos',
        redirectTo: '/finanzas/reportes/antiguedad-cxc',
        pathMatch: 'full'
      },
      {
        path: 'reportes/antiguedad-cxc',
        loadComponent: () => import('@views/finanzas/antiguedad-saldos/antiguedad-saldos.component').then(m => m.AntiguedadSaldosComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'finanzas.reporteria.ver', tipo: 'cxc' },
        title: 'Antigüedad CxC'
      },
      {
        path: 'reportes/antiguedad-cxp',
        loadComponent: () => import('@views/finanzas/antiguedad-saldos/antiguedad-saldos.component').then(m => m.AntiguedadSaldosComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'finanzas.reporteria.ver', tipo: 'cxp' },
        title: 'Antigüedad CxP'
      },
      {
        path: 'reportes/resumen-impuestos',
        loadComponent: () => import('@views/finanzas/reportes/finanzas-reportes-resumen.component').then(m => m.FinanzasReportesResumenComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'finanzas.libro_iva.ver' },
        title: 'Resumen de impuestos'
      },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FinanzasRoutingModule { }
