import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '@guards/auth.guard';
import { LayoutComponent } from '@layout/layout.component';

import { EmpresaComponent }     from '@views/admin/empresa/empresa.component';
import { EliminarDatosComponent }     from '@views/admin/empresa/eliminar-datos/eliminar-datos.component';
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
        { path: 'configuracion', component: EmpresaComponent, title: 'Configuracion' },
        { path: 'eliminar-datos', component: EliminarDatosComponent, title: 'Eliminar datos' },
        { path: 'suscripcion', component: SuscripcionComponent, title: 'Suscripcion' },
        { path: 'sucursales', component: SucursalesComponent, title: 'Sucursales' },
        { path: 'sucursal/:id', component: SucursalComponent, title: 'Sucursal' },
        { path: 'usuarios', component: UsuariosComponent, title: 'Usuarios' },
        { path: 'usuario/:id', component: UsuarioComponent, title: 'Usuario' },
        { path: 'notificaciones', component: NotificacionesComponent, title: 'Notificaciones' },
        { path: 'ayuda', component: DocsComponent, title: 'Ayuda' },
        { path: 'reportes', component: ReportesComponent, title: 'Inteligencia de negocios'},
        { path: 'cierre-de-caja', component: CorteComponent, title: 'Cierre de caja'},
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AdminRoutingModule { }
