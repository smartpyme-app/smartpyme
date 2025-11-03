import { NgModule } from '@angular/core';
import { CommonModule, CurrencyPipe, DatePipe, DecimalPipe, PercentPipe, AsyncPipe, JsonPipe, LowerCasePipe, UpperCasePipe, TitleCasePipe, SlicePipe } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { TypeaheadModule } from 'ngx-bootstrap/typeahead';
import { AlertService } from '@services/alert.service';
import { MultimediaComponent } from './multimedia/multimedia.component';
import { PipesModule } from '@pipes/pipes.module';
import { TagInputModule } from 'ngx-chips';
import { NgSelectModule } from '@ng-select/ng-select';
import { NgxMaskDirective, NgxMaskPipe } from 'ngx-mask';
import { SafeHtmlPipe } from '@pipes/safe-html.pipe';
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
import { CrearImpuestoComponent } from './modals/crear-impuesto/crear-impuesto.component';
// PaginationComponent, TimerComponent, DescargarExcelComponent, NotificacionesContainerComponent, ImportarExcelComponent, DescargarInventarioComponent ahora son standalone (importados más abajo)
import { VerHistorialButtonComponent } from '../../app/views/planillas/empleados/shared/ver-historial-button.component';
import { ThreedsModalComponent } from '../auth/register/pago/modal/threeds-modal.component';
import { AlertsHaciendaComponent } from './parts/alerts-hacienda/alerts-hacienda.component';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { AuthorizationRequestModalComponent } from './authorization/authorization-request/authorization-request-modal.component';
import { AuthorizationViewComponent } from './authorization/authorization-view/authorization-view.component';
import { CrearDepartamentoComponent } from './modals/crear-departamento-empresa/crear-departamento-empresa.component';
import { CrearAreaEmpresaComponent } from './modals/crear-area-empresa/crear-area-empresa.component';
import { SelectSearchComponent } from './parts/select-search/select-search.component';
// Componentes y pipes standalone
import { NotFoundComponent } from './404/not-found.component';
import { PaginationComponent } from './parts/pagination/pagination.component';
import { TimerComponent } from './parts/timer/timer.component';
import { DescargarExcelComponent } from './parts/descargar-excel/descargar-excel.component';
import { NotificacionesContainerComponent } from './parts/notificaciones/notificaciones-container.component';
import { ImportarExcelComponent } from './parts/importar-excel/importar-excel.component';
import { DescargarInventarioComponent } from './parts/descargar-inventario/descargar-inventario.component';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    PipesModule,
    TagInputModule,
    NgSelectModule,
    NgxMaskDirective, NgxMaskPipe,
    TooltipModule.forRoot(),
    PopoverModule.forRoot(),
    TypeaheadModule.forRoot(),
    // Componentes y pipes standalone
    NotFoundComponent,
    SafeHtmlPipe,
    PaginationComponent,
    TimerComponent,
    DescargarExcelComponent,
    NotificacionesContainerComponent,
    ImportarExcelComponent,
    DescargarInventarioComponent,
    // Todos los componentes son standalone ahora
    BusquedaClienteComponent,
    BusquedaProductoComponent,
    CrearProductoComponent,
    ClienteDireccionComponent,
    MultimediaComponent,
    BuscadorProductosComponent,
    BuscadorClientesComponent,
    BuscadorMateriasPrimasComponent,
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
    CrearImpuestoComponent,
    VerHistorialButtonComponent,
    ThreedsModalComponent,
    AlertsHaciendaComponent,
    AuthorizationRequestModalComponent,
    AuthorizationViewComponent,
    CrearDepartamentoComponent,
    CrearAreaEmpresaComponent,
    SelectSearchComponent
  ],
  declarations: [
    // Todos los componentes son standalone, se importan arriba
  ],
  exports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    PipesModule,
    TagInputModule,
    NgSelectModule,
    NgxMaskDirective,
    NgxMaskPipe,
    BusquedaClienteComponent,
    BusquedaProductoComponent,
    CrearProductoComponent,
    ClienteDireccionComponent,
    MultimediaComponent,
    BuscadorProductosComponent,
    BuscadorClientesComponent,
    BuscadorMateriasPrimasComponent,
    // PaginationComponent, TimerComponent ahora son standalone (exportados como imports standalone)
    // NotificacionesContainerComponent ahora es standalone (exportado como import standalone)
    // NotFoundComponent ahora es standalone (exportado como import standalone)
    // ImportarExcelComponent ahora es standalone (exportado como import standalone)
    // DescargarExcelComponent ahora es standalone (exportado como import standalone)
    // DescargarInventarioComponent ahora es standalone (exportado como import standalone)
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
    CrearImpuestoComponent,
    CrearProyectoComponent,
    ThreedsModalComponent,
    VerHistorialButtonComponent,
    // SafeHtmlPipe ahora es standalone (exportado como import standalone)
    AlertsHaciendaComponent,
    AuthorizationRequestModalComponent,
    AuthorizationViewComponent,
    CrearDepartamentoComponent,
    CrearAreaEmpresaComponent,
    SelectSearchComponent,
    // Componentes y pipes standalone
    NotFoundComponent,
    SafeHtmlPipe,
    PaginationComponent,
    TimerComponent,
    DescargarExcelComponent,
    NotificacionesContainerComponent,
    ImportarExcelComponent,
    DescargarInventarioComponent
  ],
  providers: [
    AlertService
  ],
})
export class SharedModule { }
