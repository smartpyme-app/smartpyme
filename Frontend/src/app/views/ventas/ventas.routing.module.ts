import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { AdminGuard } from '../../guards/admin.guard';
import { PermissionGuard } from '../../guards/permission.guard';
import { RoleGuard } from '../../guards/role.guard';

export const GUARD_TYPES = {
  ADMIN: 'admin',
  CITAS: 'citas',
  SUPER_ADMIN: 'superAdmin',
} as const;
const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Ventas',
    children: [
      {
        path: 'ventas',
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'ventas.ver',
        },
        loadComponent: () => import('@views/ventas/ventas.component').then(m => m.VentasComponent),
        title: 'Ventas',
      },
      {
        path: 'venta/crear',
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'ventas.crear',
        },
        loadComponent: () => import('@views/ventas/facturacion/facturacion-tienda/facturacion.component').then(m => m.FacturacionComponent),
        title: 'Facturación',
      },
      {
        path: 'venta/consigna/revisar/:id',
        loadComponent: () => import('@views/ventas/facturacion/facturacion-consigna/facturacion-consigna.component').then(m => m.FacturacionConsignaComponent),
        title: 'Facturación consigna',
      },
      {
        path: 'venta/:id',
        loadComponent: () => import('@views/ventas/venta/venta.component').then(m => m.VentaComponent),
        canActivate: [PermissionGuard],
        data: { type: 'venta', permission: 'ventas.ver' },
        title: 'Venta',
      },

      {
        path: 'ventas/recurrentes',
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        loadComponent: () => import('@views/ventas/recurrentes/recurrentes.component').then(m => m.RecurrentesComponent),
        title: 'Abonos de ventas',
      },
      {
        path: 'ventas/abonos',
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        loadComponent: () => import('@views/ventas/abonos/abonos-ventas.component').then(m => m.AbonosVentasComponent),
        title: 'Abonos de ventas',
      },
      {
        path: 'cotizaciones',
        loadComponent: () => import('@views/ventas/cotizaciones/cotizaciones.component').then(m => m.CotizacionesComponent),
        title: 'Cotizaciones',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.cotizaciones.ver' },
      },
      {
        path: 'cotizacion/crear',
        loadComponent: () => import('./cotizaciones/cotizacion/cotizacion.component').then(m => m.CotizacionComponent),
        title: 'Cotización',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.cotizaciones.crear' },
      },
      {
        path: 'cotizacion/editar/:id',
        loadComponent: () => import('@views/ventas/facturacion/facturacion-tienda/facturacion.component').then(m => m.FacturacionComponent),
        title: 'Cotización',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.cotizaciones.editar' },
      },
      {
        path: 'cotizacion/ver/:id',
        loadComponent: () => import('./facturacion/facturacion-tienda/cotizacion-form/cotizacion-form.component').then(m => m.CotizacionFormComponent),
        title: 'Cotización',
      },
      {
        path: 'cotizacion/:id',
        loadComponent: () => import('@views/ventas/venta/venta.component').then(m => m.VentaComponent),
        data: { type: 'cotizacion' },
        title: 'Cotización',
      },
      { 
        path: 'solicitudes-compra', 
        loadComponent: () => import('@views/ventas/solicitudes-compra/solicitudes-compra.component').then(m => m.SolicitudesCompraComponent), 
        title: 'Solicitudes de compra' 
      },
      {
        path: 'canales',
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'ventas.canales_venta.ver',
        },
        loadComponent: () => import('@views/ventas/canales/canales.component').then(m => m.CanalesComponent),
        title: 'Canales de venta',
      },
      {
        path: 'formas-de-pago',
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'ventas.formas_pago.ver',
        },
        loadComponent: () => import('@views/ventas/formas-de-pago/formas-de-pago.component').then(m => m.FormasDePagoComponent),
        title: 'Formas de pago',
      },
      {
        path: 'impuestos',
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'finanzas.impuestos.ver',
        },
        loadComponent: () => import('@views/ventas/impuestos/impuestos.component').then(m => m.ImpuestosComponent),
        title: 'Impuestos',
      },
      {
        path: 'documentos',
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'finanzas.documentos.ver',
        },
        loadComponent: () => import('@views/ventas/documentos/documentos.component').then(m => m.DocumentosComponent),
        title: 'Documentos',
      },
      {
        path: 'documento/historial/:nombre',
        canActivate: [AdminGuard],
        loadComponent: () => import('@views/ventas/documentos/historial/documento-historial.component').then(m => m.DocumentoHistorialComponent),
        title: 'Historial de documentos'
      },


      {
        path: 'devoluciones/ventas',
        loadComponent: () => import('@views/ventas/devoluciones/devoluciones-ventas.component').then(m => m.DevolucionesVentasComponent),
        title: 'Devoluciones de ventas',
      },
      {
        path: 'devolucion/venta/:id',
        loadComponent: () => import('@views/ventas/devoluciones/devolucion/devolucion-venta.component').then(m => m.DevolucionVentaComponent),
        title: 'Devolución de venta',
      },
      {
        path: 'devolucion-venta/nueva',
        loadComponent: () => import('@views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component').then(m => m.DevolucionVentaNuevaComponent),
        title: 'Devolución de venta',
      },
      // Clientes
      {
        path: 'clientes',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.clientes.ver' },
        loadComponent: () => import('@views/ventas/clientes/clientes.component').then(m => m.ClientesComponent),
        title: 'Clientes',
      },
      {
        path: 'cliente/detalles/:id',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.clientes.ver' },
        loadComponent: () => import('@views/ventas/clientes/cliente-detalles/cliente-detalles.component').then(m => m.ClienteDetallesComponent),
        title: 'Cliente',
      },
      {
        path: 'cliente/crear',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.clientes.crear' },
        loadComponent: () => import('@views/ventas/clientes/cliente/cliente.component').then(m => m.ClienteComponent),
        title: 'Cliente',
      },
      {
        path: 'cliente/editar/:id',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.clientes.editar' },
        loadComponent: () => import('@views/ventas/clientes/cliente/cliente.component').then(m => m.ClienteComponent),
        title: 'Cliente',
      },
      { 
        path: 'clientes/cuentas-cobrar', 
        loadComponent: () => import('@views/ventas/clientes/cuentas-cobrar/cuentas-cobrar.component').then(m => m.CuentasCobrarComponent) 
      },
      { 
        path: 'clientes/crm', 
        loadComponent: () => import('@views/ventas/clientes/dash/clientes-dash.component').then(m => m.ClientesDashComponent) 
      },

      // Reportes
      {
        path: 'reporte/ventas/historial',
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        loadComponent: () => import('@views/reportes/ventas/historial/historial-ventas.component').then(m => m.HistorialVentasComponent),
      },
      {
        path: 'reporte/ventas/detalle',
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        loadComponent: () => import('@views/reportes/ventas/detalle/detalle-ventas.component').then(m => m.DetalleVentasComponent),
      },
      {
        path: 'reporte/ventas/categorias',
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        loadComponent: () => import('@views/reportes/ventas/categorias/categorias-ventas.component').then(m => m.CategoriasVentasComponent),
      },
      {
        path: 'ordenes/produccion',
        loadComponent: () => import('@views/ventas/orden_produccion/ordenes-produccion.component').then(m => m.OrdenesProduccionComponent),
        title: 'Ordenes de producción',
      },
      {
        path: 'orden-produccion/crear/:id',
        loadComponent: () => import('@views/ventas/orden_produccion/crear_orden/crear-orden-produccion.component').then(m => m.CrearOrdenProduccionComponent),
        title: 'Crear Orden de Producción',
      },
      { 
        path: 'orden-produccion/detalles/:id', 
        loadComponent: () => import('@views/ventas/orden_produccion/crear_orden/crear-orden-produccion.component').then(m => m.CrearOrdenProduccionComponent), 
        title: 'Ver Orden de Producción' 
      },
      { 
        path: 'orden-produccion/editar/:id', 
        loadComponent: () => import('@views/ventas/orden_produccion/crear_orden/crear-orden-produccion.component').then(m => m.CrearOrdenProduccionComponent), 
        title: 'Editar Orden de Producción' 
      },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class VentasRoutingModule {}
