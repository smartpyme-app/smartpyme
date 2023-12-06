import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '@guards/auth.guard';
import { LayoutComponent } from '@layout/layout.component';

import { EmpresaComponent }     from '@views/admin/empresa/empresa.component';
import { SuscripcionComponent }     from '@views/admin/suscripcion/suscripcion.component';

import { SucursalesComponent }     from '@views/admin/sucursales/sucursales.component';
import { SucursalComponent }     from '@views/admin/sucursales/sucursal/sucursal.component';

import { UsuariosComponent }     from '@views/admin/usuarios/usuarios.component';
import { UsuarioComponent }     from '@views/admin/usuarios/usuario/usuario.component';

import { NotificacionesComponent }     from '@views/admin/notificaciones/notificaciones.component';
import { DocsComponent }     from '@views/admin/docs/docs.component';

import { ReportesComponent }    from '@views/reportes/reportes.component';
import { CorteComponent }    from '@views/reportes/corte/corte.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    children: [
        { path: 'configuracion', component: EmpresaComponent },
        { path: 'suscripcion', component: SuscripcionComponent },
        { path: 'sucursales', component: SucursalesComponent },
        { path: 'sucursal/:id', component: SucursalComponent },
        { path: 'usuarios', component: UsuariosComponent },
        { path: 'usuario/:id', component: UsuarioComponent },
        { path: 'notificaciones', component: NotificacionesComponent },
        { path: 'ayuda', component: DocsComponent, title: 'Ayuda' },
        { path: 'reportes', component: ReportesComponent, title: 'reportes', canActivate: [AuthGuard]},
        { path: 'cierre-de-caja', component: CorteComponent, title: 'Cierre de caja'},
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AdminRoutingModule { }
