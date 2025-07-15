import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';
import { AdminGuard } from '../../guards/admin.guard';
import { CotizacionesComponent } from '@views/ventas/cotizaciones/cotizaciones.component';

import { VentasComponent } from '@views/ventas/ventas.component';
import { VentaComponent } from '@views/ventas/venta/venta.component';

import { RecurrentesComponent } from '@views/ventas/recurrentes/recurrentes.component';
import { AbonosVentasComponent } from '@views/ventas/abonos/abonos-ventas.component';

import { CanalesComponent } from '@views/ventas/canales/canales.component';
import { FormasDePagoComponent } from '@views/ventas/formas-de-pago/formas-de-pago.component';
import { ImpuestosComponent } from '@views/ventas/impuestos/impuestos.component';
import { DocumentosComponent } from '@views/ventas/documentos/documentos.component';

import { DevolucionesVentasComponent } from '@views/ventas/devoluciones/devoluciones-ventas.component';
import { DevolucionVentaComponent } from '@views/ventas/devoluciones/devolucion/devolucion-venta.component';
import { DevolucionVentaNuevaComponent } from '@views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component';
import { FacturacionComponent } from '@views/ventas/facturacion/facturacion-tienda/facturacion.component';
import { FacturacionConsignaComponent } from '@views/ventas/facturacion/facturacion-consigna/facturacion-consigna.component';

import { ClientesComponent } from '@views/ventas/clientes/clientes.component';
import { CuentasCobrarComponent } from '@views/ventas/clientes/cuentas-cobrar/cuentas-cobrar.component';
import { ClienteComponent } from '@views/ventas/clientes/cliente/cliente.component';
import { ClienteDetallesComponent } from '@views/ventas/clientes/cliente-detalles/cliente-detalles.component';
import { ClientesDashComponent } from '@views/ventas/clientes/dash/clientes-dash.component';

import { HistorialVentasComponent } from '@views/reportes/ventas/historial/historial-ventas.component';
import { DetalleVentasComponent } from '@views/reportes/ventas/detalle/detalle-ventas.component';
import { CategoriasVentasComponent } from '@views/reportes/ventas/categorias/categorias-ventas.component';
import { CotizacionFormComponent } from './facturacion/facturacion-tienda/cotizacion-form/cotizacion-form.component';

import { PermissionGuard } from '../../guards/permission.guard';

import { RoleGuard } from '../../guards/role.guard';

export const GUARD_TYPES = {
  ADMIN: 'admin',
  CITAS: 'citas',
  SUPER_ADMIN: 'superAdmin',
} as const;

import { OrdenesProduccionComponent } from '@views/ventas/orden_produccion/ordenes-produccion.component';
import { CrearOrdenProduccionComponent } from '@views/ventas/orden_produccion/crear_orden/crear-orden-produccion.component';
const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Ventas',
    children: [
      {
        path: 'ventas',
        //canActivate: [AdminGuard, PermissionGuard],
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'ventas.ver',
        },
        component: VentasComponent,
        title: 'Ventas',
      },
      {
        path: 'venta/crear',
        //canActivate: [PermissionGuard],
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'ventas.crear',
        },
        component: FacturacionComponent,
        title: 'Facturación',
      },
      {
        path: 'venta/consigna/revisar/:id',
        component: FacturacionConsignaComponent,
        title: 'Facturación consigna',
      },
      // { path: 'venta/:id', component: VentaComponent, title: 'Venta' },
      // { path: 'cotizacion/:id', component: VentaComponent, title: 'Cotización' },
      {
        path: 'venta/:id',
        component: VentaComponent,
        canActivate: [PermissionGuard],
        data: { type: 'venta', permission: 'ventas.ver' },
        title: 'Venta',
      },

      {
        path: 'ventas/recurrentes',
        // canActivate: [AdminGuard],
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        component: RecurrentesComponent,
        title: 'Abonos de ventas',
      },
      {
        path: 'ventas/abonos',
        // canActivate: [AdminGuard],
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        component: AbonosVentasComponent,
        title: 'Abonos de ventas',
      },

      {
        path: 'cotizaciones',
        component: CotizacionesComponent,
        title: 'Cotizaciones',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.cotizaciones.ver' },
      },
      //  { path: 'cotizacion/crear', component: CotizacionFormComponent, title: 'Cotización' },
      {
        path: 'cotizacion/crear',
        component: FacturacionComponent,
        title: 'Cotización',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.cotizaciones.crear' },
      },

      {
        path: 'cotizacion/editar/:id',
        component: FacturacionComponent,
        title: 'Cotización',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.cotizaciones.editar' },
      },
      {
        path: 'cotizacion/ver/:id',
        component: CotizacionFormComponent,
        title: 'Cotización',
      },
      {
        path: 'cotizacion/:id',
        component: VentaComponent,
        data: { type: 'cotizacion' },
        title: 'Cotización',
      },
      //
      {
        path: 'canales',
        //  canActivate: [AdminGuard, PermissionGuard],
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'ventas.canales_venta.ver',
        },
        component: CanalesComponent,
        title: 'Canales de venta',
      },
      {
        path: 'formas-de-pago',
        // canActivate: [AdminGuard, PermissionGuard],
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'ventas.formas_pago.ver',
        },
        component: FormasDePagoComponent,
        title: 'Formas de pago',
      },
      {
        path: 'impuestos',
        // canActivate: [AdminGuard, PermissionGuard],
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'finanzas.impuestos.ver',
        },
        component: ImpuestosComponent,
        title: 'Impuestos',
      },
      {
        path: 'documentos',
        // canActivate: [AdminGuard, PermissionGuard],
        canActivate: [RoleGuard, PermissionGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
          permission: 'finanzas.documentos.ver',
        },
        component: DocumentosComponent,
        title: 'Documentos',
      },

      {
        path: 'devoluciones/ventas',
        component: DevolucionesVentasComponent,
        title: 'Devoluciones de ventas',
      },
      {
        path: 'devolucion/venta/:id',
        component: DevolucionVentaComponent,
        title: 'Devolución de venta',
      },
      {
        path: 'devolucion-venta/nueva',
        component: DevolucionVentaNuevaComponent,
        title: 'Devolución de venta',
      },

      // Clientes
      {
        path: 'clientes',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.clientes.ver' },
        component: ClientesComponent,
        title: 'Clientes',
      },
      {
        path: 'cliente/detalles/:id',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.clientes.ver' },
        component: ClienteDetallesComponent,
        title: 'Cliente',
      },
      {
        path: 'cliente/crear',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.clientes.crear' },
        component: ClienteComponent,
        title: 'Cliente',
      },
      {
        path: 'cliente/editar/:id',
        canActivate: [PermissionGuard],
        data: { permission: 'ventas.clientes.editar' },
        component: ClienteComponent,
        title: 'Cliente',
      },
      { path: 'clientes/cuentas-cobrar', component: CuentasCobrarComponent },
      { path: 'clientes/crm', component: ClientesDashComponent },

      // Reportes
      {
        path: 'reporte/ventas/historial',
        // canActivate: [AdminGuard],
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        component: HistorialVentasComponent,
      },
      {
        path: 'reporte/ventas/detalle',
        // canActivate: [AdminGuard],
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        component: DetalleVentasComponent,
      },
      {
        path: 'reporte/ventas/categorias',
        // canActivate: [AdminGuard],
        canActivate: [RoleGuard],
        data: {
          guardType: GUARD_TYPES.ADMIN,
        },
        component: CategoriasVentasComponent,
      },
      {
        path: 'ordenes/produccion',
        component: OrdenesProduccionComponent,
        title: 'Ordenes de producción',
      },
      {
        path: 'orden-produccion/crear/:id',
        component: CrearOrdenProduccionComponent,
        title: 'Crear Orden de Producción',
      },
      //{ path: 'orden-produccion/:id', component: CrearOrdenProduccionComponent, title: 'Editar Orden de Producción' },
      { path: 'orden-produccion/detalles/:id', component: CrearOrdenProduccionComponent, title: 'Ver Orden de Producción' },
      { path: 'orden-produccion/editar/:id', component: CrearOrdenProduccionComponent, title: 'Editar Orden de Producción' },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class VentasRoutingModule {}
