import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LayoutComponent } from '../../layout/layout.component';

import { ProductosComponent } from '../../views/inventario/productos/productos.component';
import { ProductoComponent } from '../../views/inventario/productos/producto/producto.component';
import { PromocionesComponent } from '../../views/inventario/promociones/promociones.component';

import { ProductosConsignasComponent } from '../../views/inventario/consignas/productos-consignas.component';

import { MateriasPrimaComponent } from '../../views/inventario/materias-prima/materias-prima.component';
import { MateriaPrimaComponent } from '../../views/inventario/materias-prima/materia-prima/materia-prima.component';
import { KardexComponent } from '../../views/inventario/kardex/kardex.component';
import { TrasladosComponent } from '../../views/inventario/traslados/traslados.component';
import { TrasladoComponent } from '../../views/inventario/traslados/traslado/traslado.component';
import { AjustesComponent } from '../../views/inventario/ajustes/ajustes.component';
import { AjusteComponent } from '../../views/inventario/ajustes/ajuste/ajuste.component';
import { CategoriasComponent } from '../../views/inventario/categorias/categorias.component';

import { ServiciosComponent } from '../../views/inventario/servicios/servicios.component';



const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,
    title: 'Inventario',
    children: [
        { path: 'productos', component: ProductosComponent, title: 'Productos' },
        { path: 'producto/crear', component: ProductoComponent, title: 'Producto' },
        { path: 'producto/editar/:id', component: ProductoComponent, title: 'Producto' },

        { path: 'consignas', component: ProductosConsignasComponent, title: 'Productos en consigna' },
        
        { path: 'materias-primas', component: MateriasPrimaComponent, title: 'Materias primas' },
        { path: 'materia-primas', component: MateriasPrimaComponent, title: 'Materias primas' },
        { path: 'materia-prima/crear', component: ProductoComponent, title: 'Materia prima' },
        { path: 'materia-prima/editar/:id', component: ProductoComponent, title: 'Materia prima'  },

        { path: 'producto/:id', component: ProductoComponent },
        { path: 'kardex/:id', component: KardexComponent },
        { path: 'promociones', component: PromocionesComponent},
        
        { path: 'traslados', component: TrasladosComponent, title: 'Traslados' },
        { path: 'traslado/:id', component: TrasladoComponent, title: 'Traslado'  },

        { path: 'categorias', component: CategoriasComponent, title: 'Categorias' },
        
        { path: 'ajustes', component: AjustesComponent, title: 'Ajustes'  },
        { path: 'ajuste/:id', component: AjusteComponent, title: 'Ajuste'  },
        
        { path: 'servicios', component: ServiciosComponent, title: 'Servicios'},
        { path: 'servicio/crear', component: ProductoComponent, title: 'Servicio' },
        { path: 'servicio/editar/:id', component: ProductoComponent, title: 'Servicio' },


    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class InventarioRoutingModule { }
