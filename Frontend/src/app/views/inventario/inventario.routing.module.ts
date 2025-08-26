import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '@layout/layout.component';
import { CitasGuard } from '@guards/citas.guard';
import { AdminGuard } from '@guards/admin.guard';

import { ProductosComponent } from '@views/inventario/productos/productos.component';
import { ProductoComponent } from '@views/inventario/productos/producto/producto.component';
import { PromocionesComponent } from '@views/inventario/promociones/promociones.component';
import { ProductoComboComponent } from './productos/producto/combo/producto-combo.component';

import { ProductosConsignasComponent } from '@views/inventario/consignas/productos-consignas.component';

import { MateriasPrimaComponent } from '@views/inventario/materias-prima/materias-prima.component';
import { MateriaPrimaComponent } from '@views/inventario/materias-prima/materia-prima/materia-prima.component';
import { KardexComponent } from '@views/inventario/kardex/kardex.component';
import { TrasladosComponent } from '@views/inventario/traslados/traslados.component';
import { TrasladoComponent } from '@views/inventario/traslados/traslado/traslado.component';
import { AjustesComponent } from '@views/inventario/ajustes/ajustes.component';
import { AjusteComponent } from '@views/inventario/ajustes/ajuste/ajuste.component';
import { CategoriasComponent } from '@views/inventario/categorias/categorias.component';

import { ServiciosComponent } from '@views/inventario/servicios/servicios.component';
import { BodegaComponent } from '@views/inventario/bodegas/bodega/bodega.component';
import { BodegasComponent } from '@views/inventario/bodegas/bodegas.component';
import { VerProductoComponent } from './productos/producto/ver-producto/ver-producto.component';
import { CustomFieldsComponent } from '@views/inventario/custom-fields/custom-fields.component';
import { PermissionGuard } from '@guards/permission.guard';

// Nuevos componentes de entradas y salidas
import { InventarioEntradasComponent } from '@views/inventario/entradas/inventario-entradas.component';
import { InventarioSalidasComponent } from '@views/inventario/salidas/inventario-salidas.component';
import { InventarioEntradaComponent } from '@views/inventario/entradas/entrada/inventario-entrada.component';
import { InventarioSalidaComponent } from '@views/inventario/salidas/salida/inventario-salida.component';
import { EntradaDetalleComponent } from '@views/inventario/entradas/entrada-detalle/entrada-detalle.component';
import { SalidaDetalleComponent } from '@views/inventario/salidas/salida-detalle/salida-detalle.component';

const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Inventario',
    children: [
      {
        path: 'productos',
        component: ProductosComponent,
        title: 'Productos',
        canActivate: [PermissionGuard],
        data: { permission: 'productos.ver' },
      },
      {
        path: 'producto-combos',
        component: ProductosComponent,
        title: 'Compuesto',
      },
      {
        path: 'producto/crear',
        component: ProductoComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.crear' },
        title: 'Producto',
      },
      {
        path: 'producto/ver/:id',
        component: VerProductoComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.ver' },
        title: 'Producto',
      },
      {
        path: 'producto/editar/:id',
        component: ProductoComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.editar' },
        title: 'Producto',
      },
      {
        path: 'producto/combo/crear',
        component: ProductoComboComponent,
        title: 'Producto combo',
      },

      {
        path: 'consignas',
        component: ProductosConsignasComponent,
        title: 'Productos en consigna',
      },

      {
        path: 'materias-primas',
        component: MateriasPrimaComponent,
        title: 'Materias primas',
      },
      {
        path: 'materia-primas',
        component: MateriasPrimaComponent,
        title: 'Materias primas',
      },
      {
        path: 'materia-prima/crear',
        component: ProductoComponent,
        title: 'Materia prima',
      },
      {
        path: 'materia-prima/editar/:id',
        component: ProductoComponent,
        title: 'Materia prima',
      },

      {
        path: 'producto/:id',
        component: ProductoComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.ver' },
        title: 'Producto',
      },
      { path: 'kardex/:id', component: KardexComponent },
      { path: 'promociones', component: PromocionesComponent },

      { path: 'traslados', component: TrasladosComponent, title: 'Traslados' },
      { path: 'traslado/:id', component: TrasladoComponent, title: 'Traslado' },

      {
        path: 'categorias',
        component: CategoriasComponent,
        title: 'Categorias',
      },

      { path: 'ajustes', component: AjustesComponent, title: 'Ajustes' },
      { path: 'ajuste/:id', component: AjusteComponent, title: 'Ajuste' },

      {
        path: 'servicios',
        canActivate: [CitasGuard],
        component: ServiciosComponent,
        title: 'Servicios',
      },
      {
        path: 'servicio/crear',
        canActivate: [CitasGuard],
        component: ProductoComponent,
        title: 'Servicio',
      },
      {
        path: 'servicio/editar/:id',
        canActivate: [CitasGuard],
        component: ProductoComponent,
        title: 'Servicio',
      },

      {
        path: 'bodegas',
        component: BodegasComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.bodegas.ver' },
        title: 'Bodegas',
      },
      {
        path: 'bodega/:id',
        component: BodegaComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.bodegas.ver' },
        title: 'Bodega',
      },
      {
        path: 'custom-fields',
        component: CustomFieldsComponent,
        canActivate: [PermissionGuard],
        data: { permission: 'productos.campos_personalizados.ver' },
        title: 'Campos personalizados',
      },
       // Nuevas rutas para entradas y salidas
       { path: 'entradas', component: InventarioEntradasComponent, title: 'Entradas de Inventario' },
       { path: 'entrada/:id', component: InventarioEntradaComponent, title: 'Entrada de Inventario' },
       { path: 'entrada/detalle/:id', component: EntradaDetalleComponent, title: 'Detalle de entrada' },
       { path: 'salidas', component: InventarioSalidasComponent, title: 'Salidas de Inventario' },
       { path: 'salida/:id', component: InventarioSalidaComponent, title: 'Salida de Inventario' },
       { path: 'salida/detalle/:id', component: SalidaDetalleComponent, title: 'Detalle de salida' },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class InventarioRoutingModule {}
