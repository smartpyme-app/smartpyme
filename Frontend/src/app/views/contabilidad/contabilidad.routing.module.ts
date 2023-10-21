import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminGuard } from '../../guards/admin.guard';
import { LayoutComponent } from '../../layout/layout.component';

import { LibroIvaComponent } from '../../views/contabilidad/libro-iva/libro-iva.component';
import { LibroComprasComponent } from '../../views/contabilidad/libro-compras/libro-compras.component';
import { CajasChicasComponent } from '../../views/contabilidad/cajas-chicas/cajas-chicas.component';
import { CajaChicaComponent } from '../../views/contabilidad/cajas-chicas/caja-chica/caja-chica.component';
import { ActivosComponent }     from '../../views/contabilidad/activos/activos.component';
import { ActivoComponent }     from '../../views/contabilidad/activos/activo/activo.component';
import { ActivosCategoriasComponent }     from '../../views/contabilidad/activos/categorias/activos-categorias.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
        { path: 'libro-iva', component: LibroIvaComponent },
        { path: 'libro-compras', component: LibroComprasComponent },
      
        { path: 'cajas-chicas', component: CajasChicasComponent, canActivate: [AdminGuard]},
        { path: 'caja-chica/:id', component: CajaChicaComponent },

        { path: 'activos', component: ActivosComponent, canActivate: [AdminGuard]},
        { path: 'activo/:id', component: ActivoComponent, canActivate: [AdminGuard]},

        { path: 'activos/categorias', component: ActivosCategoriasComponent, canActivate: [AdminGuard]},
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ContabilidadRoutingModule { }
