import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { ComprasComponent } from '../compras/compras.component';
import { FacturacionCompraComponent } from '../compras/facturacion/facturacion-compra.component';
import { FacturacionCompraConsignaComponent } from '../compras/facturacion/facturacion-consigna/facturacion-compra-consigna.component';
import { CompraComponent } from '../compras/compra/compra.component';
import { DevolucionesComprasComponent } from '../compras/devoluciones/devoluciones-compras.component';
import { DevolucionCompraComponent } from '../compras/devoluciones/devolucion/devolucion-compra.component';
import { DevolucionCompraNuevaComponent } from '../compras/devoluciones/devolucion-nueva/devolucion-compra-nueva.component';

import { ComprasRecurrentesComponent } from './recurrentes/compras-recurrentes.component';
import { AbonosComprasComponent } from './abonos/abonos-compras.component';

import { CotizacionesComprasComponent } from './cotizaciones/cotizaciones-compras.component';

import { ProveedoresComponent } from '../compras/proveedores/proveedores.component';
import { ProveedorComponent } from '../compras/proveedores/proveedor/proveedor.component';
import { ProveedorDetallesComponent } from '../compras/proveedores/proveedor-detalles/proveedor-detalles.component';
import { CuentasPagarComponent } from '../compras/proveedores/cuentas-pagar/cuentas-pagar.component';

import { HistorialComprasComponent } from '../reportes/compras/historial/historial-compras.component';
import { DetalleComprasComponent } from '../reportes/compras/detalle/detalle-compras.component';
import { CategoriasComprasComponent } from '../reportes/compras/categorias/categorias-compras.component';

import { GastosComponent } from './gastos/gastos.component';
import { GastosRecurrentesComponent } from './gastos/recurrentes/gastos-recurrentes.component';
import { GastoComponent } from './gastos/gasto/gasto.component';
import { GastoDetallesComponent } from './gastos/gasto-detalles/gasto-detalles.component';
import { GastosDashComponent } from './gastos/dash/gastos-dash.component';
import { GastosCategoriasComponent } from './gastos/categorias/gastos-categorias.component';
import { AbonosGastosComponent } from './gastos/abonos/abonos-gastos.component';
import { DepartamentoEmpresaComponent } from './gastos/departamento-empresa/departamento-empresa.component';
import { AreaEmpresaComponent } from './gastos/area-empresa/area-empresa.component';
import { GastosSupervisorRestriccionGuard } from '@guards/gastos-supervisor-restriccion.guard';
import { PermissionGuard } from '../../guards/permission.guard';
import { FuncionalidadGuard } from '@guards/funcionalidad.guard';
import { PERM_VER_COMPRAS, PERM_VER_GASTOS } from './compras-routing-access';

const gastosSupervisorRestriccion = [GastosSupervisorRestriccionGuard];
const gastosGuards = [PermissionGuard, ...gastosSupervisorRestriccion] as const;

const retaceoGuards = [PermissionGuard, FuncionalidadGuard];

const routes: Routes = [
  {
    path: '',
    title: 'Compras',
    children: [
      {
        path: 'compras',
        loadComponent: () => import('../compras/compras.component').then(m => m.ComprasComponent),
        canActivate: [PermissionGuard],
        data: {
          permissions: [...PERM_VER_COMPRAS],
        },
        title: 'Compras',
      },
      {
        path: 'compra/crear',
        loadComponent: () => import('../compras/facturacion/facturacion-compra.component').then(m => m.FacturacionCompraComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.crear',
        },
        title: 'Compra',
      },
      {
        path: 'compra/editar/:id',
        loadComponent: () => import('../compras/facturacion/facturacion-compra.component').then(m => m.FacturacionCompraComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.editar',
        },
        title: 'Compra',
      },
      {
        path: 'compra/consigna/revisar/:id',
        loadComponent: () => import('../compras/facturacion/facturacion-consigna/facturacion-compra-consigna.component').then(m => m.FacturacionCompraConsignaComponent),
        canActivate: [PermissionGuard],
        data: {
          permissions: ['compras.consignas.ver', 'compras.editar', ...PERM_VER_COMPRAS],
        },
        title: 'Compra consigna',
      },
      {
        path: 'compra/:id',
        loadComponent: () => import('../compras/compra/compra.component').then(m => m.CompraComponent),
        canActivate: [PermissionGuard],
        data: {
          permissions: [...PERM_VER_COMPRAS, 'compras.editar'],
        },
        title: 'Compra',
      },

      {
        path: 'compras/recurrentes',
        loadComponent: () => import('./recurrentes/compras-recurrentes.component').then(m => m.ComprasRecurrentesComponent),
        canActivate: [PermissionGuard],
        data: { permissions: [...PERM_VER_COMPRAS] },
        title: 'Compras recurrentes',
      },
      {
        path: 'compras/abonos',
        loadComponent: () => import('./abonos/abonos-compras.component').then(m => m.AbonosComprasComponent),
        canActivate: [PermissionGuard],
        data: { permissions: [...PERM_VER_COMPRAS] },
        title: 'Abonos de compra',
      },
      {
        path: 'ordenes-de-compras',
        loadComponent: () => import('./cotizaciones/cotizaciones-compras.component').then(m => m.CotizacionesComprasComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.ordenes_compra.ver',
        },
        title: 'Ordenes de compra',
      },
      {
        path: 'orden-de-compra/crear',
        loadComponent: () => import('./cotizaciones/components/orden-compra-form/orden-compra-form.component').then(m => m.OrdenCompraFormComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.ordenes_compra.crear',
        },
        title: 'Orden de compra',
      },
      {
        path: 'orden-de-compra/:id',
        loadComponent: () => import('./cotizaciones/components/orden-compra-form/orden-compra-form.component').then(m => m.OrdenCompraFormComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.ordenes_compra.ver',
        },
        title: 'Orden de compra',
      },

      {
        path: 'devoluciones/compras',
        loadComponent: () => import('../compras/devoluciones/devoluciones-compras.component').then(m => m.DevolucionesComprasComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'compras.devoluciones.ver' },
        title: 'Devoluciones de compras',
      },
      {
        path: 'devolucion/compra/:id',
        loadComponent: () => import('../compras/devoluciones/devolucion/devolucion-compra.component').then(m => m.DevolucionCompraComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'compras.devoluciones.ver' },
        title: 'Devolución de compra',
      },
      {
        path: 'devolucion-compra/nueva',
        loadComponent: () => import('../compras/devoluciones/devolucion-nueva/devolucion-compra-nueva.component').then(m => m.DevolucionCompraNuevaComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'compras.devoluciones.crear' },
        title: 'Devolución de compra',
      },
      {
        path: 'proveedores',
        loadComponent: () => import('../compras/proveedores/proveedores.component').then(m => m.ProveedoresComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.proveedores.ver',
        },
        title: 'Proveedores',
      },
      {
        path: 'proveedor/crear',
        loadComponent: () => import('../compras/proveedores/proveedor/proveedor.component').then(m => m.ProveedorComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.proveedores.crear',
        },
        title: 'Proveedor',
      },
      {
        path: 'proveedor/detalles/:id',
        loadComponent: () => import('../compras/proveedores/proveedor-detalles/proveedor-detalles.component').then(m => m.ProveedorDetallesComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.proveedores.ver',
        },
        title: 'Proveedor',
      },
      {
        path: 'proveedor/editar/:id',
        loadComponent: () => import('../compras/proveedores/proveedor/proveedor.component').then(m => m.ProveedorComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.proveedores.editar',
        },
        title: 'Proveedor',
      },
      {
        path: 'proveedores/cuentas-pagar',
        loadComponent: () => import('../compras/proveedores/cuentas-pagar/cuentas-pagar.component').then(m => m.CuentasPagarComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'compras.proveedores.ver' },
        title: 'Cuentas por pagar',
      },

      {
        path: 'reporte/compras/historial',
        loadComponent: () => import('../reportes/compras/historial/historial-compras.component').then(m => m.HistorialComprasComponent),
        canActivate: [PermissionGuard],
        data: { permissions: [...PERM_VER_COMPRAS] },
      },
      {
        path: 'reporte/compras/detalle',
        loadComponent: () => import('../reportes/compras/detalle/detalle-compras.component').then(m => m.DetalleComprasComponent),
        canActivate: [PermissionGuard],
        data: { permissions: [...PERM_VER_COMPRAS] },
      },
      {
        path: 'reporte/compras/categorias',
        loadComponent: () => import('../reportes/compras/categorias/categorias-compras.component').then(m => m.CategoriasComprasComponent),
        canActivate: [PermissionGuard],
        data: { permissions: [...PERM_VER_COMPRAS] },
      },
      {
        path: 'gastos',
        loadComponent: () => import('./gastos/gastos.component').then(m => m.GastosComponent),
        canActivate: [PermissionGuard],
        data: {
          permissions: [...PERM_VER_GASTOS],
        },
        title: 'Gastos',
      },
      {
        path: 'gastos/recurrentes',
        loadComponent: () => import('./gastos/recurrentes/gastos-recurrentes.component').then(m => m.GastosRecurrentesComponent),
        canActivate: [...gastosGuards],
        data: { permissions: [...PERM_VER_GASTOS] },
        title: 'Gastos recurrentes',
      },
      {
        path: 'gastos/abonos',
        loadComponent: () => import('./gastos/abonos/abonos-gastos.component').then(m => m.AbonosGastosComponent),
        canActivate: [...gastosGuards],
        data: { permissions: [...PERM_VER_GASTOS] },
        title: 'Abonos de gastos',
      },
      {
        path: 'gasto/crear',
        loadComponent: () => import('./gastos/gasto/gasto.component').then(m => m.GastoComponent),
        canActivate: [...gastosGuards],
        data: {
          permissions: ['gastos.crear', ...PERM_VER_GASTOS],
        },
        title: 'Crear gasto',
      },
      {
        path: 'gasto/detalles/:id',
        loadComponent: () => import('./gastos/gasto-detalles/gasto-detalles.component').then(m => m.GastoDetallesComponent),
        canActivate: [...gastosGuards],
        data: {
          permissions: [...PERM_VER_GASTOS],
        },
        title: 'Gasto',
      },
      {
        path: 'gasto/:id',
        loadComponent: () => import('./gastos/gasto/gasto.component').then(m => m.GastoComponent),
        canActivate: [...gastosGuards],
        data: {
          permissions: [...PERM_VER_GASTOS, 'gastos.editar', 'gastos.crear'],
        },
        title: 'Gasto',
      },
      {
        path: 'gastos/dash',
        loadComponent: () => import('./gastos/dash/gastos-dash.component').then(m => m.GastosDashComponent),
        canActivate: [...gastosGuards],
        data: { permissions: [...PERM_VER_GASTOS] },
      },
      {
        path: 'gastos/categorias',
        loadComponent: () => import('./gastos/categorias/gastos-categorias.component').then(m => m.GastosCategoriasComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'gastos.categorias.ver',
        },
        title: 'Categorías de gastos',
      },

      {
        path: 'retaceos',
        loadComponent: () => import('./retaceo/retaceos-list.component').then(m => m.RetaceosListComponent),
        canActivate: retaceoGuards,
        data: {
          permission: 'compras.retaceo.ver',
          funcionalidadSlug: 'contabilidad',
        },
        title: 'Retaceos'
      },
      {
        path: 'retaceo/crear',
        loadComponent: () => import('./retaceo/retaceo.component').then(m => m.RetaceoComponent),
        canActivate: retaceoGuards,
        data: {
          permission: 'compras.retaceo.crear',
          funcionalidadSlug: 'contabilidad',
        },
        title: 'Retaceo'
      },
      {
        path: 'retaceo/:id',
        loadComponent: () => import('./retaceo/retaceo.component').then(m => m.RetaceoComponent),
        canActivate: retaceoGuards,
        data: {
          permission: 'compras.retaceo.ver',
          funcionalidadSlug: 'contabilidad',
        },
        title: 'Retaceo'
      },
      {
        path: 'departamentos-empresa',
        loadComponent: () => import('./gastos/departamento-empresa/departamento-empresa.component').then(m => m.DepartamentoEmpresaComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'administracion.departamentos.ver' },
        title:'Departamentos de empresa'
      },
      {
        path: 'areas-empresa',
        loadComponent: () => import('./gastos/area-empresa/area-empresa.component').then(m => m.AreaEmpresaComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'administracion.areas.ver' },
        title:'Áreas de empresa'
      },
      {
        path: 'gastos/departamentos-empresa',
        loadComponent: () => import('./gastos/departamento-empresa/departamento-empresa.component').then(m => m.DepartamentoEmpresaComponent),
        canActivate: [...gastosGuards],
        data: { permission: 'administracion.departamentos.ver' },
        title: 'Departamentos de empresa',
      },
      {
        path: 'gastos/areas-empresa',
        loadComponent: () => import('./gastos/area-empresa/area-empresa.component').then(m => m.AreaEmpresaComponent),
        canActivate: [...gastosGuards],
        data: { permission: 'administracion.areas.ver' },
        title: 'Áreas de empresa',
      },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ComprasRoutingModule {}
