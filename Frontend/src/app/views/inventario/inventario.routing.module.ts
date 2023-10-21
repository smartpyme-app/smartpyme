import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { ProductosComponent } from '../../views/inventario/productos/productos.component';
import { ProductoComponent } from '../../views/inventario/productos/producto/producto.component';
import { PromocionesComponent } from '../../views/inventario/promociones/promociones.component';
import { MateriasPrimaComponent } from '../../views/inventario/materias-prima/materias-prima.component';
import { MateriaPrimaComponent } from '../../views/inventario/materias-prima/materia-prima/materia-prima.component';
import { KardexComponent } from '../../views/inventario/kardex/kardex.component';
import { TrasladosComponent } from '../../views/inventario/traslados/traslados.component';
import { TrasladoComponent } from '../../views/inventario/traslados/traslado/traslado.component';
import { AjustesComponent } from '../../views/inventario/ajustes/ajustes.component';
import { AjusteComponent } from '../../views/inventario/ajustes/ajuste/ajuste.component';
import { AnalisisProductosComponent } from '../../views/inventario/analisis/analisis-productos.component';


import { BodegaComponent } from '../../views/inventario/bodegas/bodega/bodega.component';
import { BodegasComponent } from '../../views/inventario/bodegas/bodegas.component';
import { CategoriasComponent } from '../../views/inventario/categorias/categorias.component';

import { ServiciosComponent } from '../../views/inventario/servicios/servicios.component';



const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Inventario',
    children: [
        { path: 'productos', component: ProductosComponent},
        { path: 'producto/crear', component: ProductoComponent },
        { path: 'producto/editar/:id', component: ProductoComponent },

        { path: 'materias-primas', component: MateriasPrimaComponent},
        { path: 'materia-prima/:id', component: MateriaPrimaComponent},
        { path: 'producto/:id', component: ProductoComponent },
        { path: 'kardex/:id', component: KardexComponent },
        { path: 'promociones', component: PromocionesComponent},
        
        { path: 'traslados', component: TrasladosComponent },
        { path: 'traslado/:id', component: TrasladoComponent },

        { path: 'categorias', component: CategoriasComponent },
        
        { path: 'ajustes', component: AjustesComponent },
        { path: 'ajuste/:id', component: AjusteComponent },
        
        { path: 'bodegas', component: BodegasComponent },
        { path: 'bodega/:id', component: BodegaComponent },

        { path: 'analisis', component: AjustesComponent },
        { path: 'analisis/productos', component: AnalisisProductosComponent },

        { path: 'servicios', component: ServiciosComponent},
        { path: 'servicio/:id', component: ProductoComponent },


    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class InventarioRoutingModule { }
