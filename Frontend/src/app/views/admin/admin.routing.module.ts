import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminGuard } from '@guards/admin.guard';
import { UsuariosGuard } from '@guards/usuarios.guard';
import { LayoutComponent } from '@layout/layout.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    canActivate: [AdminGuard],
    children: [
        { 
          path: 'mi-cuenta', 
          loadComponent: () => import('@views/admin/empresa/empresa.component').then(m => m.EmpresaComponent), 
          title: 'Mi cuenta' 
        },
        { 
          path: 'eliminar-datos', 
          loadComponent: () => import('@views/admin/empresa/eliminar-datos/eliminar-datos.component').then(m => m.EliminarDatosComponent), 
          title: 'Eliminar datos' 
        },
        { 
          path: 'suscripcion', 
          loadComponent: () => import('@views/admin/suscripcion/suscripcion.component').then(m => m.SuscripcionComponent), 
          title: 'Suscripción' 
        },
        { 
          path: 'sucursales', 
          loadComponent: () => import('@views/admin/sucursales/sucursales.component').then(m => m.SucursalesComponent), 
          title: 'Sucursales' 
        },
        // { 
        //   path: 'sucursal/:id', 
        //   loadComponent: () => import('@views/admin/sucursales/sucursal/sucursal.component').then(m => m.SucursalComponent), 
        //   title: 'Sucursal' 
        // },
        { 
          path: 'usuarios', 
          loadComponent: () => import('@views/admin/usuarios/usuarios.component').then(m => m.UsuariosComponent), 
          title: 'Usuarios', 
          canActivate: [UsuariosGuard] 
        },
        { 
          path: 'usuario/:id', 
          loadComponent: () => import('@views/admin/usuarios/usuario/usuario.component').then(m => m.UsuarioComponent), 
          title: 'Usuario', 
          canActivate: [UsuariosGuard] 
        },
        { 
          path: 'notificaciones', 
          loadComponent: () => import('@views/admin/notificaciones/notificaciones.component').then(m => m.NotificacionesComponent), 
          title: 'Notificaciones' 
        },
        { 
          path: 'ayuda', 
          loadComponent: () => import('@views/admin/docs/docs.component').then(m => m.DocsComponent), 
          title: 'Ayuda' 
        },
        { 
          path: 'reportes', 
          loadComponent: () => import('@views/reportes/reportes.component').then(m => m.ReportesComponent), 
          title: 'Inteligencia de negocios'
        },
        { 
          path: 'reportes-automaticos', 
          loadComponent: () => import('@views/reportes/reportes-automaticos.component').then(m => m.ReportesAutomaticosComponent), 
          title: 'Reportes automáticos'
        },
        { 
          path: 'whatsapp', 
          loadComponent: () => import('@views/admin/whatsapp/whatsapp.component').then(m => m.WhatsAppComponent), 
          title: 'WhatsApp' 
        },
        { 
          path: 'whatsapp/estadisticas', 
          loadComponent: () => import('@views/admin/whatsapp/estadisticas/whatsapp-estadisticas.component').then(m => m.WhatsAppEstadisticasComponent), 
          title: 'Estadísticas de WhatsApp' 
        },
        { 
          path: 'roles-permisos', 
          loadComponent: () => import('@views/admin/roles-permisos/roles-permisos.component').then(m => m.RolesPermisosComponent), 
          title: 'Roles y permisos'
        },
        { 
          path: 'authorization/:code', 
          loadComponent: () => import('@shared/authorization/authorization-view/authorization-view.component').then(m => m.AuthorizationViewComponent), 
          title: 'Autorización' 
        },
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AdminRoutingModule { }
