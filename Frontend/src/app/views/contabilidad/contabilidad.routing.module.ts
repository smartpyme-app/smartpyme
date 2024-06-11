import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminGuard } from '@guards/admin.guard';
import { LayoutComponent } from '@layout/layout.component';

import { ConsumidorFinalComponent } from '@views/contabilidad/libro-iva/consumidor-final/consumidor-final.component';
import { ContribuyentesComponent } from '@views/contabilidad/libro-iva/contribuyentes/contribuyentes.component';
import { LibroComprasComponent } from '@views/contabilidad/libro-compras/libro-compras.component';
import { PresupuestosComponent } from '@views/contabilidad/presupuestos/presupuestos.component';
import { PresupuestoComponent } from '@views/contabilidad/presupuestos/presupuesto/presupuesto.component';
import { PresupuestoDetallesComponent } from '@views/contabilidad/presupuestos/presupuesto-detalles/presupuesto-detalles.component';

import { CuentasComponent } from '@views/contabilidad/bancos/cuentas/cuentas.component';
import { CuentaComponent } from '@views/contabilidad/bancos/cuentas/cuenta/cuenta.component';
import { ChequesComponent } from '@views/contabilidad/bancos/cheques/cheques.component';
import { ChequeComponent } from '@views/contabilidad/bancos/cheques/cheque/cheque.component';
import { TransaccionesComponent } from '@views/contabilidad/bancos/transacciones/transacciones.component';
import { TransaccionComponent } from '@views/contabilidad/bancos/transacciones/transaccion/transaccion.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
        { path: 'presupuestos', component: PresupuestosComponent },

        { path: 'presupuesto/crear', component: PresupuestoComponent, title: 'Presupuesto'},
        { path: 'presupuesto/editar/:id', component: PresupuestoComponent, title: 'Presupuesto'},
        { path: 'presupuesto/detalles/:id', component: PresupuestoDetallesComponent, title: 'Presupuesto'},

        { path: 'libro-iva/contribuyentes', component: ContribuyentesComponent },
        { path: 'libro-iva/consumidor-final', component: ConsumidorFinalComponent },
        { path: 'libro-compras', component: LibroComprasComponent },


        { path: 'bancos/cuentas', component: CuentasComponent, title: 'Cuentas' },
        { path: 'bancos/cuenta/:id', component: CuentaComponent, title: 'Cuenta' },

        { path: 'bancos/cheques', component: ChequesComponent, title: 'Cheques' },
        { path: 'bancos/cheque/:id', component: ChequeComponent, title: 'Cheque' },

        { path: 'bancos/transacciones', component: TransaccionesComponent, title: 'Transacciones' },
        { path: 'bancos/transaccion/:id', component: TransaccionComponent, title: 'Transacción' },

    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ContabilidadRoutingModule { }
