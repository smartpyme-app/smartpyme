import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { FocusModule } from 'angular2-focus';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { MultimediaComponent } from './multimedia/multimedia.component';
import { PipesModule } from '@pipes/pipes.module';
import { TagInputModule } from 'ngx-chips';
import { NgSelectModule } from '@ng-select/ng-select';
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask';
import { BusquedaClienteComponent } from './modals/busqueda-cliente/busqueda-cliente.component';
import { BusquedaProductoComponent } from './modals/busqueda-producto/busqueda-producto.component';
import { ClienteDireccionComponent } from './modals/cliente-direccion/cliente-direccion.component';

import { BuscadorProductosComponent } from './parts/buscador-productos/buscador-productos.component';
import { CrearProductoComponent } from './modals/crear-producto/crear-producto.component';
import { BuscadorClientesComponent } from './parts/buscador-clientes/buscador-clientes.component';
import { BuscadorMateriasPrimasComponent } from './parts/buscador-materias-primas/buscador-materias-primas.component';
import { CrearCategoriaComponent } from './modals/crear-categoria/crear-categoria.component';
import { CrearSubCategoriaComponent } from './modals/crear-subcategoria/crear-subcategoria.component';
import { CrearCategoriaActivoComponent } from './modals/crear-categoria-activo/crear-categoria-activo.component';
import { CrearCategoriaGastoComponent } from './modals/crear-categoria-gasto/crear-categoria-gasto.component';
import { CrearCargoEmpleadoComponent } from './modals/crear-cargo-empleado/crear-cargo-empleado.component';
import { CrearProveedorComponent } from './modals/crear-proveedor/crear-proveedor.component';
import { CrearClienteComponent } from './modals/crear-cliente/crear-cliente.component';
import { CrearAjusteComponent } from './modals/crear-ajuste/crear-ajuste.component';
import { CrearAbonoVentaComponent } from './modals/crear-abono-venta/crear-abono-venta.component';
import { CrearAbonoCompraComponent } from './modals/crear-abono-compra/crear-abono-compra.component';
import { CrearEventoComponent } from './modals/crear-evento/crear-evento.component';
import { CrearProyectoComponent } from './modals/crear-proyecto/crear-proyecto.component';

import { PaginationComponent } from './parts/pagination/pagination.component';
import { NotificacionesContainerComponent } from './parts/notificaciones/notificaciones-container.component';
import { TimerComponent } from './parts/timer/timer.component';

import { NotFoundComponent } from './404/not-found.component';

import { ImportarExcelComponent } from './parts/importar-excel/importar-excel.component';
import { DescargarExcelComponent } from './parts/descargar-excel/descargar-excel.component';
import { VerHistorialButtonComponent } from '../../app/views/planillas/empleados/shared/ver-historial-button.component';

import { ThreedsModalComponent } from '../auth/register/pago/modal/threeds-modal.component';



@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PipesModule,
    TagInputModule,
    NgSelectModule,
    NgxMaskDirective, NgxMaskPipe,
    TooltipModule.forRoot(),
    FocusModule.forRoot()
  ],
  declarations: [
    BusquedaClienteComponent,
    BusquedaProductoComponent,
    CrearProductoComponent,
    ClienteDireccionComponent,
    MultimediaComponent,
    BuscadorProductosComponent,
    BuscadorClientesComponent,
    BuscadorMateriasPrimasComponent,
    PaginationComponent,
    TimerComponent,
    NotificacionesContainerComponent,
    NotFoundComponent,
    ImportarExcelComponent,
    DescargarExcelComponent,
    CrearCategoriaActivoComponent,
    CrearCategoriaComponent,
    CrearSubCategoriaComponent,
    CrearCategoriaGastoComponent,
    CrearCargoEmpleadoComponent,
    CrearProveedorComponent,
    CrearClienteComponent,
    CrearAjusteComponent,
    CrearAbonoVentaComponent,
    CrearAbonoCompraComponent,
    CrearEventoComponent,
    CrearProyectoComponent,
    ThreedsModalComponent,
    VerHistorialButtonComponent
  ],
  exports: [
    BusquedaClienteComponent,
    BusquedaProductoComponent,
    CrearProductoComponent,
    ClienteDireccionComponent,
    MultimediaComponent,
    BuscadorProductosComponent,
    BuscadorClientesComponent,
    BuscadorMateriasPrimasComponent,
    PaginationComponent,
    TimerComponent,
    NotificacionesContainerComponent,
    NotFoundComponent,
    ImportarExcelComponent,
    DescargarExcelComponent,
    CrearCategoriaActivoComponent,
    CrearCategoriaComponent,
    CrearSubCategoriaComponent,
    CrearCategoriaGastoComponent,
    CrearCargoEmpleadoComponent,
    CrearProveedorComponent,
    CrearClienteComponent,
    CrearAjusteComponent,
    CrearAbonoVentaComponent,
    CrearAbonoCompraComponent,
    CrearEventoComponent,
    CrearProyectoComponent,
    ThreedsModalComponent,
    VerHistorialButtonComponent
  ],
  providers: [AlertService],
})
export class SharedModule { }
