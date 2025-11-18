import { NgModule } from '@angular/core';
import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

// TooltipModule removido - los módulos lo importan directamente cuando lo necesitan (LayoutModule ya lo tiene)
// TypeaheadModule removido - los componentes lo importan directamente cuando lo necesitan
import { AlertService } from '@services/alert.service';
// MultimediaComponent removido - los componentes lo importan directamente cuando lo necesitan
import { PipesModule } from '@pipes/pipes.module';
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
import { CrearAbonoGastoComponent } from './modals/crear-abono-gasto/crear-abono-gasto.component';
// CrearEventoComponent removido - solo se usa en citas, los componentes lo importan directamente
// CrearProyectoComponent removido - los componentes lo importan directamente cuando lo necesitan
import { CrearImpuestoComponent } from './modals/crear-impuesto/crear-impuesto.component';
// VerHistorialButtonComponent removido - solo se usa en planillas (módulo lazy)
// ThreedsModalComponent removido - solo se usa en auth, los componentes lo importan directamente
// AlertsHaciendaComponent removido - solo se usa en módulos lazy
// PopoverModule removido - los componentes lo importan directamente cuando lo necesitan
// AuthorizationRequestModalComponent removido - se carga dinámicamente cuando se necesita
// AuthorizationViewComponent removido - solo se usa en módulos lazy
// CrearDepartamentoComponent removido - solo se usa en módulos lazy
// CrearAreaEmpresaComponent removido - solo se usa en módulos lazy
// SelectSearchComponent removido - solo se usa en módulos lazy
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
    NgSelectModule,
    NgxMaskDirective, NgxMaskPipe,
    // TooltipModule removido - los módulos lo importan directamente
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
    // MultimediaComponent removido - los componentes lo importan directamente
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
    CrearAbonoGastoComponent,
    // CrearEventoComponent removido - solo se usa en citas, los componentes lo importan directamente
    // CrearProyectoComponent removido - los componentes lo importan directamente cuando lo necesitan
    CrearImpuestoComponent,
    // VerHistorialButtonComponent removido - solo se usa en planillas (módulo lazy)
    // ThreedsModalComponent removido - solo se usa en auth, los componentes lo importan directamente
    // AlertsHaciendaComponent removido - solo se usa en módulos lazy
    // AuthorizationRequestModalComponent removido - se carga dinámicamente cuando se necesita
    // AuthorizationViewComponent removido - solo se usa en módulos lazy
    // CrearDepartamentoComponent removido - solo se usa en módulos lazy
    // CrearAreaEmpresaComponent removido - solo se usa en módulos lazy
    // SelectSearchComponent removido - solo se usa en módulos lazy
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
    NgSelectModule,
    NgxMaskDirective,
    NgxMaskPipe,
    BusquedaClienteComponent,
    BusquedaProductoComponent,
    CrearProductoComponent,
    ClienteDireccionComponent,
    // MultimediaComponent removido
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
    CrearAbonoGastoComponent,
    // CrearEventoComponent removido
    CrearImpuestoComponent,
    // CrearProyectoComponent removido
    // ThreedsModalComponent removido
    // VerHistorialButtonComponent removido - solo se usa en planillas (módulo lazy)
    // SafeHtmlPipe ahora es standalone (exportado como import standalone)
    // AlertsHaciendaComponent removido - solo se usa en módulos lazy
    // AuthorizationRequestModalComponent removido - se carga dinámicamente cuando se necesita
    // AuthorizationViewComponent removido - solo se usa en módulos lazy
    // CrearDepartamentoComponent removido - solo se usa en módulos lazy
    // CrearAreaEmpresaComponent removido - solo se usa en módulos lazy
    // SelectSearchComponent removido - solo se usa en módulos lazy
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
