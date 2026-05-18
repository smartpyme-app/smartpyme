import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '@layout/layout.component';

import { ConsumidorFinalComponent } from '@views/contabilidad/libro-iva/consumidor-final/consumidor-final.component';
import { ContribuyentesComponent } from '@views/contabilidad/libro-iva/contribuyentes/contribuyentes.component';
import { LibroIvaGeneralComponent } from '@views/contabilidad/libro-iva/libro-iva-general/libro-iva-general.component';
import { LibroIvaResumenComponent } from '@views/contabilidad/libro-iva/libro-iva-resumen/libro-iva-resumen.component';
import { LibroComprasComponent } from '@views/contabilidad/libro-compras/libro-compras.component';
import { LibroAnuladosComponent } from '@views/contabilidad/libro-anulados/libro-anulados.component';
import { LibroComprasSujetosExcluidosComponent } from '@views/contabilidad/libro-compras-sujetos-excluidos/libro-compras-sujetos-excluidos.component';

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
        {
          path: 'libro-iva/contribuyentes',
          loadComponent: () => import('@views/contabilidad/libro-iva/contribuyentes/contribuyentes.component').then(m => m.ContribuyentesComponent)
        },
        {
          path: 'libro-iva/consumidor-final',
          loadComponent: () => import('@views/contabilidad/libro-iva/consumidor-final/consumidor-final.component').then(m => m.ConsumidorFinalComponent)
        },
        {
          path: 'libro-iva/resumen',
          loadComponent: () => import('@views/contabilidad/libro-iva/libro-iva-resumen/libro-iva-resumen.component').then(m => m.LibroIvaResumenComponent),
          title: 'Resumen fiscal'
        },
        {
          path: 'libro-iva/general',
          loadComponent: () => import('@views/contabilidad/libro-iva/libro-iva-general/libro-iva-general.component').then(m => m.LibroIvaGeneralComponent),
          title: 'Libros fiscales'
        },
        {
          path: 'libro-compras',
          loadComponent: () => import('@views/contabilidad/libro-compras/libro-compras.component').then(m => m.LibroComprasComponent)
        },
        {
          path: 'libro-anulados',
          loadComponent: () => import('@views/contabilidad/libro-anulados/libro-anulados.component').then(m => m.LibroAnuladosComponent)
        },
        {
          path: 'libro-compras-sujetos-excluidos',
          loadComponent: () => import('@views/contabilidad/libro-compras-sujetos-excluidos/libro-compras-sujetos-excluidos.component').then(m => m.LibroComprasSujetosExcluidosComponent)
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

        { path: 'libro-iva/contribuyentes', component: ContribuyentesComponent,
          title: 'Contribuyentes'},
        { path: 'libro-iva/consumidor-final', component: ConsumidorFinalComponent,
          title: 'Consumidor final'},
        { path: 'libro-iva/resumen', component: LibroIvaResumenComponent,
          title: 'Resumen fiscal'},
        { path: 'libro-iva/general', component: LibroIvaGeneralComponent,
          title: 'Libros fiscales'},
        { path: 'libro-compras', component: LibroComprasComponent, title: 'Libro de compras'},
        { path: 'libro-anulados', component: LibroAnuladosComponent, title: 'Libro de anulados'},
        { path: 'libro-compras-sujetos-excluidos', component: LibroComprasSujetosExcluidosComponent, title: 'Libro de compras sujetos excluidos'},

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
