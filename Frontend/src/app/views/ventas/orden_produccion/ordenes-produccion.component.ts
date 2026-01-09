import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-ordenes-produccion',
    templateUrl: './ordenes-produccion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent, PopoverModule, TooltipModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class OrdenesProduccionComponent extends BaseCrudComponent<any> implements OnInit {
  public ordenes:any = {};
  public orden: any = {}
  public downloading: boolean = false;
  public clientes: any = [];
  public usuarios: any = [];
  public asesores: any = [];
  public override modalRef!: BsModalRef;

  constructor(
    apiService: ApiService, 
    alertService: AlertService,
    modalManager: ModalManagerService,
    private modalService: BsModalService,
    private cdr: ChangeDetectorRef
  ) {
    super(apiService, alertService, modalManager, {
      endpoint: 'orden-produccion',
      itemsProperty: 'ordenes',
      itemProperty: 'orden',
      reloadAfterSave: false,
      reloadAfterDelete: false,
      messages: {
        created: 'La orden fue guardada exitosamente.',
        updated: 'La orden fue guardada exitosamente.',
        createTitle: 'Orden guardada',
        updateTitle: 'Orden guardada'
      }
    });
  }

  protected aplicarFiltros(): void {
    this.filtrarOrdenes();
  }

  ngOnInit() {
    this.loadAll();
    
    this.apiService.getAll('clientes/list')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (clientes) => {
          this.clientes = clientes;
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.filtrarOrdenes();
  }

  public override loadAll() {
    this.filtros = {
      id_cliente: '',
      id_usuario: '',
      id_asesor: '',
      estado: '',
      buscador: '',
      orden: 'fecha',
      direccion: 'desc',
      paginate: 10
    };
    this.filtrarOrdenes();
  }

  public filtrarOrdenes() {
    this.loading = true;
    this.apiService.getAll('ordenes-produccion', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (ordenes) => {
          this.ordenes = ordenes.data;
          this.loading = false;
          if (this.modalRef) {
            this.modalRef.hide();
          }
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.loading = false;
          this.cdr.markForCheck();
        }
      });
  }

  public setEstado(orden: any) {
    this.apiService.store('orden-produccion/cambiar-estado', orden)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.alertService.success('Orden actualizada', 'El estado de la orden fue actualizado exitosamente.');
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });
  }

  changeStateOrden(ordenId: number, estado: string) {
    this.apiService.store('orden-produccion/cambiar-estado-orden', { id: ordenId, estado: estado })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.alertService.success('Orden actualizada', 'El estado de la orden fue actualizado exitosamente.');
          this.filtrarOrdenes();
          this.cdr.markForCheck();
        },
        error: (error) => {
          if (error.status === 400 && error.error && error.error.message) {
            let mensaje = error.error.message;
            
            if (error.error.detalles_incompletos && error.error.detalles_incompletos.length > 0) {
              mensaje += '<br><br><strong>Productos pendientes:</strong><ul>';
              error.error.detalles_incompletos.forEach((detalle: any) => {
                mensaje += `<li>Producto ID: ${detalle.producto_id} - Faltante: ${detalle.cantidad_faltante} unidades</li>`;
              });
              mensaje += '</ul>';
            }
            
            this.alertService.error(mensaje);
          } else {
            this.alertService.error('Ocurrió un error al actualizar el estado de la orden.');
          }
          this.cdr.markForCheck();
        }
      });
  }

  public imprimir(orden: any) {
    window.open(this.apiService.baseUrl + '/api/orden-produccion/imprimir/' + orden.id + '?token=' + this.apiService.auth_token());
  }

  public openFilter(template: TemplateRef<any>) {
    if (!this.usuarios.length) {
      this.apiService.getAll('usuarios/list')
        .pipe(this.untilDestroyed())
        .subscribe({
          next: (usuarios) => {
            this.asesores = usuarios;
            this.cdr.markForCheck();
          },
          error: (error) => {
            this.alertService.error(error);
            this.cdr.markForCheck();
          }
        });
    }

    this.modalRef = this.modalService.show(template);
  }

  public descargar() {
    this.downloading = true;
    this.apiService.export('ordenes-produccion/exportar', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (data: Blob) => {
          const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'ordenes-produccion.xlsx';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
          this.downloading = false;
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.downloading = false;
          this.cdr.markForCheck();
        }
      });
  }

  public anular(orden: any) {
    this.apiService.store('orden-produccion/anular', orden)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.alertService.success('Orden anulada', 'La orden fue anulada exitosamente.');
          orden.estado = 'anulada';
          this.filtrarOrdenes();
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      });
  }
}
