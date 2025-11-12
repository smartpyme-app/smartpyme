import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '@layout/layout.component';
import { CitasGuard } from '@guards/citas.guard';
import { PermissionGuard } from '@guards/permission.guard';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Inventario',
    children: [
      {
        path: 'productos',
        loadComponent: () => import('@views/inventario/productos/productos.component').then(m => m.ProductosComponent),
        title: 'Productos',
        canActivate: [PermissionGuard],
        data: { permission: 'productos.ver' },
      },
      {
        path: 'producto-combos',
        loadComponent: () => import('@views/inventario/productos/productos.component').then(m => m.ProductosComponent),
        title: 'Compuesto',
      },
      {
        path: 'producto/crear',
        loadComponent: () => import('@views/inventario/productos/producto/producto.component').then(m => m.ProductoComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.crear' },
        title: 'Producto',
      },
      {
        path: 'producto/ver/:id',
        loadComponent: () => import('./productos/producto/ver-producto/ver-producto.component').then(m => m.VerProductoComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.ver' },
        title: 'Producto',
      },
      {
        path: 'producto/editar/:id',
        loadComponent: () => import('@views/inventario/productos/producto/producto.component').then(m => m.ProductoComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.editar' },
        title: 'Producto',
      },
      {
        path: 'producto/combo/crear',
        loadComponent: () => import('./productos/producto/combo/producto-combo.component').then(m => m.ProductoComboComponent),
        title: 'Producto combo',
      },


      {
        path: 'consignas',
        loadComponent: () => import('@views/inventario/consignas/productos-consignas.component').then(m => m.ProductosConsignasComponent),
        title: 'Productos en consigna',
      },
      {
        path: 'materias-primas',
        loadComponent: () => import('@views/inventario/materias-prima/materias-prima.component').then(m => m.MateriasPrimaComponent),
        title: 'Materias primas',
      },
      {
        path: 'materia-primas',
        loadComponent: () => import('@views/inventario/materias-prima/materias-prima.component').then(m => m.MateriasPrimaComponent),
        title: 'Materias primas',
      },
      {
        path: 'materia-prima/crear',
        loadComponent: () => import('@views/inventario/productos/producto/producto.component').then(m => m.ProductoComponent),
        title: 'Materia prima',
      },
      {
        path: 'materia-prima/editar/:id',
        loadComponent: () => import('@views/inventario/productos/producto/producto.component').then(m => m.ProductoComponent),
        title: 'Materia prima',
      },
      {
        path: 'producto/:id',
        loadComponent: () => import('@views/inventario/productos/producto/producto.component').then(m => m.ProductoComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.ver' },
        title: 'Producto',
      },
      { 
        path: 'kardex/:id', 
        loadComponent: () => import('@views/inventario/kardex/kardex.component').then(m => m.KardexComponent) 
      },
      { 
        path: 'promociones', 
        loadComponent: () => import('@views/inventario/promociones/promociones.component').then(m => m.PromocionesComponent) 
      },

      { 
        path: 'traslados', 
        loadComponent: () => import('@views/inventario/traslados/traslados.component').then(m => m.TrasladosComponent), 
        title: 'Traslados' 
      },
      { 
        path: 'traslado/:id', 
        loadComponent: () => import('@views/inventario/traslados/traslado/traslado.component').then(m => m.TrasladoComponent), 
        title: 'Traslado' 
      },
      {
        path: 'categorias',
        loadComponent: () => import('@views/inventario/categorias/categorias.component').then(m => m.CategoriasComponent),
        title: 'Categorias',
      },
      { 
        path: 'ajustes', 
        loadComponent: () => import('@views/inventario/ajustes/ajustes.component').then(m => m.AjustesComponent), 
        title: 'Ajustes' 
      },
      { 
        path: 'ajuste/crear', 
        loadComponent: () => import('@views/inventario/productos/producto/ajuste/ajuste-masivo.component').then(m => m.AjusteMasivoComponent), 
        title: 'Ajuste masivo' 
      },
      { 
        path: 'ajuste/:id', 
        loadComponent: () => import('@views/inventario/ajustes/ajuste/ajuste.component').then(m => m.AjusteComponent), 
        title: 'Ajuste' 
      },
      { 
        path: 'traslado-masivo/crear', 
        loadComponent: () => import('@views/inventario/productos/producto/traslado/traslado-masivo.component').then(m => m.TrasladoMasivoComponent), 
        title: 'Traslado masivo' 
      },

      {
        path: 'servicios',
        canActivate: [CitasGuard],
        loadComponent: () => import('@views/inventario/servicios/servicios.component').then(m => m.ServiciosComponent),
        title: 'Servicios',
      },
      {
        path: 'servicio/crear',
        canActivate: [CitasGuard],
        loadComponent: () => import('@views/inventario/productos/producto/producto.component').then(m => m.ProductoComponent),
        title: 'Servicio',
      },
      {
        path: 'servicio/editar/:id',
        canActivate: [CitasGuard],
        loadComponent: () => import('@views/inventario/productos/producto/producto.component').then(m => m.ProductoComponent),
        title: 'Servicio',
      },
      // Nuevas rutas para entradas y salidas
      { 
        path: 'entradas',
        loadComponent: () => import('@views/inventario/entradas/inventario-entradas.component').then(m => m.InventarioEntradasComponent),
        title: 'Entradas de Inventario'
      },
      { 
        path: 'entrada/:id',
        loadComponent: () => import('@views/inventario/entradas/entrada/inventario-entrada.component').then(m => m.InventarioEntradaComponent),
        title: 'Entrada de Inventario'
      },
      { 
        path: 'entrada/detalle/:id',
        loadComponent: () => import('@views/inventario/entradas/entrada-detalle/entrada-detalle.component').then(m => m.EntradaDetalleComponent),
        title: 'Detalle de entrada'
      },
      { 
        path: 'salidas',
        loadComponent: () => import('@views/inventario/salidas/inventario-salidas.component').then(m => m.InventarioSalidasComponent),
        title: 'Salidas de Inventario'
      },
      { 
        path: 'salida/:id',
        loadComponent: () => import('@views/inventario/salidas/salida/inventario-salida.component').then(m => m.InventarioSalidaComponent),
        title: 'Salida de Inventario'
      },
      { 
        path: 'salida/detalle/:id',
        loadComponent: () => import('@views/inventario/salidas/salida-detalle/salida-detalle.component').then(m => m.SalidaDetalleComponent),
        title: 'Detalle de salida'
      },


      {
        path: 'bodegas',
        loadComponent: () => import('@views/inventario/bodegas/bodegas.component').then(m => m.BodegasComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.bodegas.ver' },
        title: 'Bodegas',
      },
      {
        path: 'bodega/:id',
        loadComponent: () => import('@views/inventario/bodegas/bodega/bodega.component').then(m => m.BodegaComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.bodegas.ver' },
        title: 'Bodega',
      },
      {
        path: 'custom-fields',
        loadComponent: () => import('@views/inventario/custom-fields/custom-fields.component').then(m => m.CustomFieldsComponent),
        canActivate: [PermissionGuard],
        data: { permission: 'productos.campos_personalizados.ver' },
        title: 'Campos personalizados',
      },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class InventarioRoutingModule {}
