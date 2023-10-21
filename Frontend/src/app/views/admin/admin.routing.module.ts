import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '../../guards/auth.guard';
import { LayoutComponent } from '../../layout/layout.component';

import { EmpresaComponent }     from '../../views/admin/empresa/empresa.component';

import { SucursalesComponent }     from '../../views/admin/sucursales/sucursales.component';
import { SucursalComponent }     from '../../views/admin/sucursales/sucursal/sucursal.component';

import { UsuariosComponent }     from '../../views/admin/usuarios/usuarios.component';
import { UsuarioComponent }     from '../../views/admin/usuarios/usuario/usuario.component';
import { CajasComponent }     from '../../views/admin/cajas/cajas.component';

import { CajaComponent }     from '../../views/admin/cajas/caja/caja.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
        { path: 'negocio', component: EmpresaComponent },
        { path: 'sucursales', component: SucursalesComponent },
        { path: 'sucursal/:id', component: SucursalComponent },
        { path: 'cajas', component: CajasComponent },
        { path: 'caja/:id', component: CajaComponent },
        { path: 'usuarios', component: UsuariosComponent },
        { path: 'usuario/:id', component: UsuarioComponent },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AdminRoutingModule { }
