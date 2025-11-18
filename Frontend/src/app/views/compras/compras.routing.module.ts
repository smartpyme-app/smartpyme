import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { RoleGuard } from '../../guards/role.guard';
import { PermissionGuard } from '../../guards/permission.guard';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Compras',
    children: [
      {
        path: 'compras',
        loadComponent: () => import('../compras/compras.component').then(m => m.ComprasComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'compras.ver',
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
        title: 'Compra consigna',
      },
      {
        path: 'compra/:id',
        loadComponent: () => import('../compras/compra/compra.component').then(m => m.CompraComponent),
        title: 'Compra'
      },

      {
        path: 'compras/recurrentes',
        loadComponent: () => import('./recurrentes/compras-recurrentes.component').then(m => m.ComprasRecurrentesComponent),
        title: 'Compras recurrentes',
      },
      {
        path: 'compras/abonos',
        loadComponent: () => import('./abonos/abonos-compras.component').then(m => m.AbonosComprasComponent),
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
        title: 'Devoluciones de compras',
      },
      {
        path: 'devolucion/compra/:id',
        loadComponent: () => import('../compras/devoluciones/devolucion/devolucion-compra.component').then(m => m.DevolucionCompraComponent),
        title: 'Devolución de compra',
      },
      {
        path: 'devolucion-compra/nueva',
        loadComponent: () => import('../compras/devoluciones/devolucion-nueva/devolucion-compra-nueva.component').then(m => m.DevolucionCompraNuevaComponent),
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
        title: 'Cuentas por pagar',
      },

      {
        path: 'reporte/compras/historial',
        loadComponent: () => import('../reportes/compras/historial/historial-compras.component').then(m => m.HistorialComprasComponent),
      },
      {
        path: 'reporte/compras/detalle',
        loadComponent: () => import('../reportes/compras/detalle/detalle-compras.component').then(m => m.DetalleComprasComponent)
      },
      {
        path: 'reporte/compras/categorias',
        loadComponent: () => import('../reportes/compras/categorias/categorias-compras.component').then(m => m.CategoriasComprasComponent),
      },
      {
        path: 'gastos',
        loadComponent: () => import('./gastos/gastos.component').then(m => m.GastosComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'gastos.ver',
        },
        title: 'Gastos',
      },
      {
        path: 'gastos/recurrentes',
        loadComponent: () => import('./gastos/recurrentes/gastos-recurrentes.component').then(m => m.GastosRecurrentesComponent),
        title: 'Gastos recurrentes',
      },
      {
        path: 'gastos/abonos',
        loadComponent: () => import('./gastos/abonos/abonos-gastos.component').then(m => m.AbonosGastosComponent),
        title: 'Abonos de gastos',
      },
      {
        path: 'gasto/detalles/:id',
        loadComponent: () => import('./gastos/gasto-detalles/gasto-detalles.component').then(m => m.GastoDetallesComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'gastos.ver',
        },
        title: 'Gasto',
      },
      {
        path: 'gasto/:id',
        loadComponent: () => import('./gastos/gasto/gasto.component').then(m => m.GastoComponent),
        canActivate: [PermissionGuard],
        data: {
          permission: 'gastos.ver',
        },
        title: 'Gasto',
      },
      {
        path: 'gastos/dash',
        loadComponent: () => import('./gastos/dash/gastos-dash.component').then(m => m.GastosDashComponent)
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
        title: 'Retaceos'
      },
      {
        path: 'retaceo/crear',
        loadComponent: () => import('./retaceo/retaceo.component').then(m => m.RetaceoComponent),
        title: 'Retaceo'
      },
      {
        path: 'retaceo/:id',
        loadComponent: () => import('./retaceo/retaceo.component').then(m => m.RetaceoComponent),
        title: 'Retaceo'
      },
      {
        path: 'departamentos-empresa',
        loadComponent: () => import('./gastos/departamento-empresa/departamento-empresa.component').then(m => m.DepartamentoEmpresaComponent),
        title:'Departamentos de empresa'
      },
      {
        path: 'areas-empresa',
        loadComponent: () => import('./gastos/area-empresa/area-empresa.component').then(m => m.AreaEmpresaComponent),
        title:'Áreas de empresa'
      },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ComprasRoutingModule {}
