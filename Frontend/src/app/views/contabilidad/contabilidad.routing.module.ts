import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '@layout/layout.component';

import { PresupuestosComponent } from '@views/contabilidad/presupuestos/presupuestos.component';
import { PresupuestoComponent } from '@views/contabilidad/presupuestos/presupuesto/presupuesto.component';
import { PresupuestoDetallesComponent } from '@views/contabilidad/presupuestos/presupuesto-detalles/presupuesto-detalles.component';

import { CuentasComponent } from '@views/contabilidad/bancos/cuentas/cuentas.component';
import { CuentaComponent } from '@views/contabilidad/bancos/cuentas/cuenta/cuenta.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
        {
          path: 'presupuestos',
          loadComponent: () => import('@views/contabilidad/presupuestos/presupuestos.component').then(m => m.PresupuestosComponent),
          title: 'Presupuestos'
        },
        {
          path: 'presupuesto/crear',
          loadComponent: () => import('@views/contabilidad/presupuestos/presupuesto/presupuesto.component').then(m => m.PresupuestoComponent),
          title: 'Presupuesto'
        },
        {
          path: 'presupuesto/editar/:id',
          loadComponent: () => import('@views/contabilidad/presupuestos/presupuesto/presupuesto.component').then(m => m.PresupuestoComponent),
          title: 'Presupuesto'
        },
        {
          path: 'presupuesto/detalles/:id',
          loadComponent: () => import('@views/contabilidad/presupuestos/presupuesto-detalles/presupuesto-detalles.component').then(m => m.PresupuestoDetallesComponent),
          title: 'Presupuesto'
        },
        // El Salvador
        {
          path: 'libro-iva-sv/contribuyentes',
          loadComponent: () => import('@views/contabilidad/libro-iva-sv/contribuyentes/contribuyentes.component').then(m => m.ContribuyentesComponent),
          title: 'Contribuyentes'
        },
        {
          path: 'libro-iva-sv/consumidor-final',
          loadComponent: () => import('@views/contabilidad/libro-iva-sv/consumidor-final/consumidor-final.component').then(m => m.ConsumidorFinalComponent),
          title: 'Consumidor final'
        },
        {
          path: 'libro-iva-sv/compras',
          loadComponent: () => import('@views/contabilidad/libro-iva-sv/compras/libro-compras.component').then(m => m.LibroComprasComponent),
          title: 'Libro de compras'
        },
        {
          path: 'libro-iva-sv/anulados',
          loadComponent: () => import('@views/contabilidad/libro-iva-sv/anulados/libro-anulados.component').then(m => m.LibroAnuladosComponent),
          title: 'Libro de anulados'
        },
        {
          path: 'libro-iva-sv/compras-sujetos-excluidos',
          loadComponent: () => import('@views/contabilidad/libro-iva-sv/compras-sujetos-excluidos/libro-compras-sujetos-excluidos.component').then(m => m.LibroComprasSujetosExcluidosComponent),
          title: 'Libro de compras sujetos excluidos'
        },
        {
          path: 'libro-iva-sv/resumen',
          loadComponent: () => import('@views/contabilidad/libro-iva-sv/resumen/libro-iva-resumen.component').then(m => m.LibroIvaResumenComponent),
          title: 'Resumen fiscal'
        },
        // Costa Rica
        {
          path: 'libro-iva-cr/ventas',
          loadComponent: () => import('@views/contabilidad/libro-iva-cr/ventas/libro-iva-cr-ventas.component').then(m => m.LibroIvaCrVentasComponent),
          title: 'Libro IVA ventas'
        },
        {
          path: 'libro-iva-cr/compras',
          loadComponent: () => import('@views/contabilidad/libro-iva-cr/compras/libro-iva-cr-compras.component').then(m => m.LibroIvaCrComprasComponent),
          title: 'Libro IVA compras'
        },
        {
          path: 'libro-iva-cr/resumen',
          loadComponent: () => import('@views/contabilidad/libro-iva-cr/resumen/libro-iva-cr-resumen.component').then(m => m.LibroIvaCrResumenComponent),
          title: 'Resumen fiscal'
        },
        // Honduras
        {
          path: 'libro-iva-hd/ventas',
          loadComponent: () => import('@views/contabilidad/libro-iva-hd/ventas/libro-iva-hd-ventas.component').then(m => m.LibroIvaHdVentasComponent),
          title: 'Libro IVA ventas'
        },
        {
          path: 'libro-iva-hd/compras',
          loadComponent: () => import('@views/contabilidad/libro-iva-hd/compras/libro-iva-hd-compras.component').then(m => m.LibroIvaHdComprasComponent),
          title: 'Libro IVA compras'
        },
        {
          path: 'libro-iva-hd/retenciones',
          loadComponent: () => import('@views/contabilidad/libro-iva-hd/retenciones/libro-iva-hd-retenciones.component').then(m => m.LibroIvaHdRetencionesComponent),
          title: 'Libro IVA retenciones'
        },
        {
          path: 'libro-iva-hd/resumen',
          loadComponent: () => import('@views/contabilidad/libro-iva-hd/resumen/libro-iva-hd-resumen.component').then(m => m.LibroIvaHdResumenComponent),
          title: 'Resumen fiscal'
        },
        // Otros países
        {
          path: 'libro-iva-general/ventas',
          loadComponent: () => import('@views/contabilidad/libro-iva-general/ventas/libro-iva-general-ventas.component').then(m => m.LibroIvaGeneralVentasComponent),
          title: 'Libro IVA ventas'
        },
        {
          path: 'libro-iva-general/compras',
          loadComponent: () => import('@views/contabilidad/libro-iva-general/compras/libro-iva-general-compras.component').then(m => m.LibroIvaGeneralComprasComponent),
          title: 'Libro IVA compras'
        },
        {
          path: 'libro-iva-general/resumen',
          loadComponent: () => import('@views/contabilidad/libro-iva-general/resumen/libro-iva-general-resumen.component').then(m => m.LibroIvaGeneralResumenComponent),
          title: 'Resumen fiscal'
        },
        // Rutas legacy → redirección
        {
          path: 'libro-iva/general',
          loadComponent: () => import('@views/contabilidad/libro-iva-general/redirect/libro-iva-redirect.component').then(m => m.LibroIvaRedirectComponent),
          title: 'Libros fiscales'
        },
        {
          path: 'libro-iva/contribuyentes',
          redirectTo: 'libro-iva-sv/contribuyentes',
          pathMatch: 'full'
        },
        {
          path: 'libro-iva/consumidor-final',
          redirectTo: 'libro-iva-sv/consumidor-final',
          pathMatch: 'full'
        },
        {
          path: 'libro-iva/resumen',
          redirectTo: 'libro-iva-sv/resumen',
          pathMatch: 'full'
        },
        {
          path: 'libro-compras',
          redirectTo: 'libro-iva-sv/compras',
          pathMatch: 'full'
        },
        {
          path: 'libro-anulados',
          redirectTo: 'libro-iva-sv/anulados',
          pathMatch: 'full'
        },
        {
          path: 'libro-compras-sujetos-excluidos',
          redirectTo: 'libro-iva-sv/compras-sujetos-excluidos',
          pathMatch: 'full'
        },
        {
          path: 'bancos/cuentas',
          loadComponent: () => import('@views/contabilidad/bancos/cuentas/cuentas.component').then(m => m.CuentasComponent),
          title: 'Cuentas'
        },
        {
          path: 'bancos/cuenta/:id',
          loadComponent: () => import('@views/contabilidad/bancos/cuentas/cuenta/cuenta.component').then(m => m.CuentaComponent),
          title: 'Cuenta'
        },
        {
          path: 'bancos/cheques',
          loadComponent: () => import('@views/contabilidad/bancos/cheques/cheques.component').then(m => m.ChequesComponent),
          title: 'Cheques'
        },
        {
          path: 'bancos/cheque/:id',
          loadComponent: () => import('@views/contabilidad/bancos/cheques/cheque/cheque.component').then(m => m.ChequeComponent),
          title: 'Cheque'
        },
        {
          path: 'bancos/transacciones',
          loadComponent: () => import('@views/contabilidad/bancos/transacciones/transacciones.component').then(m => m.TransaccionesComponent),
          title: 'Transacciones'
        },
        {
          path: 'bancos/transaccion/:id',
          loadComponent: () => import('@views/contabilidad/bancos/transacciones/transaccion/transaccion.component').then(m => m.TransaccionComponent),
          title: 'Transacción'
        },
        {
          path: 'bancos/conciliaciones',
          loadComponent: () => import('@views/contabilidad/bancos/conciliaciones/conciliaciones.component').then(m => m.ConciliacionesComponent),
          title: 'Conciliaciones'
        },
        {
          path: 'bancos/conciliacion/:id',
          loadComponent: () => import('@views/contabilidad/bancos/conciliaciones/conciliacion/conciliacion.component').then(m => m.ConciliacionComponent),
          title: 'Conciliación'
        },
        {
          path: 'catalogo/cuentas',
          loadComponent: () => import('@views/contabilidad/catalogo-cuentas/catalogo-cuentas.component').then(m => m.CatalogoCuentasComponent),
          title: 'Catálogo de cuentas'
        },
        {
          path: 'catalogo/cuenta/:id',
          loadComponent: () => import('@views/contabilidad/catalogo-cuentas/catalogo-cuenta/catalogo-cuenta.component').then(m => m.CatalogoCuentaComponent),
          title: 'Catálogo cuenta'
        },
        {
          path: 'contabilidad/partidas',
          loadComponent: () => import('@views/contabilidad/partidas/partidas.component').then(m => m.PartidasComponent),
          title: 'Partidas'
        },
        {
          path: 'contabilidad/partida/:id',
          loadComponent: () => import('@views/contabilidad/partidas/partida/partida.component').then(m => m.PartidaComponent),
          title: 'Partida'
        },
        {
          path: 'contabilidad/cierre-mes',
          loadComponent: () => import('@views/contabilidad/cierre-mes/cierre-mes.component').then(m => m.CierreMesComponent),
          title: 'Cierre de Mes'
        },
        {
          path: 'contabilidad/configuracion',
          loadComponent: () => import('@views/contabilidad/configuracion/contabilidad-configuracion.component').then(m => m.ContabilidadConfiguracionComponent),
          title: 'Configuración'
        },
        { path: 'presupuestos', component: PresupuestosComponent, title: 'Presupuestos'},

        { path: 'presupuesto/crear', component: PresupuestoComponent, title: 'Presupuesto'},
        { path: 'presupuesto/editar/:id', component: PresupuestoComponent, title: 'Presupuesto'},
        { path: 'presupuesto/detalles/:id', component: PresupuestoDetallesComponent, title: 'Presupuesto'},

        { path: 'bancos/cuentas', component: CuentasComponent, title: 'Cuentas bancarias'},
        { path: 'bancos/cuenta/crear', component: CuentaComponent, title: 'Crear cuenta'},
        { path: 'bancos/cuenta/:id', component: CuentaComponent, title: 'Editar cuenta'}
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ContabilidadRoutingModule { }
