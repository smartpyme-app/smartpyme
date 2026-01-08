import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-cotizaciones',
    templateUrl: './cotizaciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, TruncatePipe, PopoverModule, TooltipModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,

})

export class CotizacionesComponent extends BaseCrudComponent<any> implements OnInit {

  public ventas: any = {};
  public venta: any = {};
  public downloading: boolean = false;
  public clientes: any = [];
  public usuarios: any = [];
  public canales: any = [];
  public proyectos: any = [];
  public formaPagos: any = [];
  public sucursales: any = [];
  public documentos: any = [];
  public filtrado: boolean = false;

  constructor(
    apiService: ApiService,
    alertService: AlertService,
    modalManager: ModalManagerService,
    private cdr: ChangeDetectorRef
  ) {
    super(apiService, alertService, modalManager, {
      endpoint: 'cotizacion',
      itemsProperty: 'ventas',
      itemProperty: 'venta',
      reloadAfterSave: false,
      reloadAfterDelete: false,
      messages: {
        created: 'La cotización fue guardado exitosamente.',
        updated: 'La cotización fue guardado exitosamente.',
        createTitle: 'Cotización guardado',
        updateTitle: 'Cotización guardado'
      },
      afterSave: () => {
        this.venta = {};
      }
    });
  }

  protected aplicarFiltros(): void {
    this.filtrarVentas();
  }

  ngOnInit() {
    this.loadAll();

    this.apiService.getAll('clientes/list')
      .pipe(this.untilDestroyed())
      .subscribe(clientes => {
        this.clientes = clientes;
        this.cdr.markForCheck();
      }, error => { this.alertService.error(error); this.cdr.markForCheck(); });
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.cdr.markForCheck();
    this.filtrarVentas();
  }

  public override loadAll() {
    this.filtros.id_sucursal = '';
    this.filtros.id_cliente = '';
    this.filtros.id_usuario = '';
    this.filtros.id_canal = '';
    this.filtros.id_proyecto = '';
    this.filtros.forma_pago = '';
    this.filtros.estado = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'fecha';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 10;
    this.filtrarVentas();
  }

  public filtrarVentas() {
    this.loading = true;
    this.cdr.markForCheck();
    if (!this.filtros.id_cliente) this.filtros.id_cliente = '';
  
    this.apiService.getAll('cotizaciones', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(ventas => {
        this.ventas = this.normalizeVentas(ventas);
        this.loading = false;
        if (this.modalRef) {
          this.closeModal();
        }
        this.cdr.markForCheck();
      }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
  }

  private normalizeVentas(ventas: any) {
    if (ventas && Array.isArray(ventas.data)) {
      ventas.data = ventas.data.map((venta: any) => ({
        ...venta,
        estado: venta?.estado ? String(venta.estado).toLowerCase() : venta?.estado
      }));
    }
    return ventas;
  }

  public setEstado(cotizacion: any) {
    // Agregamos el distintivo
    cotizacion.cotizacion_id = 1;
    
    this.apiService.store('cotizacion', cotizacion)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.alertService.success('Cotización actualizada', 'La cotización fue actualizada exitosamente.');
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });
  }

  public override delete(item: any | number): void {
    const itemToDelete = typeof item === 'number' ? item : (item as any).id;
    
    if (!confirm('¿Desea eliminar el Registro?')) {
      return;
    }

    this.loading = true;
    this.apiService.delete('venta/', itemToDelete)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (deletedItem: any) => {
          const index = this.ventas.data?.findIndex((v: any) => v.id === deletedItem.id);
          if (index !== -1 && index >= 0) {
            this.ventas.data.splice(index, 1);
          }
          this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (error: any) => {
          this.alertService.error(error);
          this.loading = false;
          this.cdr.markForCheck();
        }
      });
  }

  public reemprimir(venta: any) {
    window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
  }

  openModalEdit(template: TemplateRef<any>, venta: any) {
    this.venta = venta;

    this.apiService.getAll('documentos')
      .pipe(this.untilDestroyed())
      .subscribe(documentos => {
        this.documentos = documentos;
        this.cdr.markForCheck();
      }, error => { this.alertService.error(error); this.cdr.markForCheck(); });

    this.openModal(template, venta);
  }

  public openFilter(template: TemplateRef<any>) {
    if (!this.sucursales.length) {
      this.apiService.getAll('sucursales/list')
        .pipe(this.untilDestroyed())
        .subscribe(sucursales => {
          this.sucursales = sucursales;
          this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.cdr.markForCheck(); });
    }

    if (!this.usuarios.length) {
      this.apiService.getAll('usuarios/list')
        .pipe(this.untilDestroyed())
        .subscribe(usuarios => {
          this.usuarios = usuarios;
          this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.cdr.markForCheck(); });
    }

    if (!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos) {
      this.apiService.getAll('proyectos/list')
        .pipe(this.untilDestroyed())
        .subscribe(proyectos => {
          this.proyectos = proyectos;
          this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.cdr.markForCheck(); });
    }

    this.openModal(template);
  }

  public imprimir(venta: any) {
    window.open(this.apiService.baseUrl + '/api/cotizacion/impresion/' + venta.id + '/cotizacion?token=' + this.apiService.auth_token());
  }

  public descargar() {
    this.downloading = true;
    //agregar a filtros que es una cotizacion_id
    this.filtros.cotizacion_id = 1;
    this.apiService.export('cotizaciones/exportar', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe((data: Blob) => {
      const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'cotizaciones.xlsx';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      this.downloading = false;
    }, (error) => { this.alertService.error(error); this.downloading = false; }
    );
  }

  changeStateCotizacion(ventaId: number, estado: string) {
    this.apiService.store('cotizacion/changeState', { id: ventaId, estado: estado })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.alertService.success(`Cotización ${estado}`, `La cotización fue ${estado} exitosamente.`);
          this.filtrarVentas();
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });
  }

  public duplicarCotizacion(id: number) {
    this.apiService.store('cotizacion/duplicar', { id: id })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.alertService.success('Cotización duplicada', 'La cotización fue duplicada exitosamente.');
          this.filtrarVentas();
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });
  }

}
