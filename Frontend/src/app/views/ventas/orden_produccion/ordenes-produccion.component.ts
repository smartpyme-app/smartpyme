import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-ordenes-produccion',
    templateUrl: './ordenes-produccion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent, PopoverModule, TooltipModule, LazyImageDirective],
    
})
export class OrdenesProduccionComponent extends BasePaginatedComponent implements OnInit {
  public ordenes: PaginatedResponse<any> = {} as PaginatedResponse;
  public orden: any = {}
  public downloading: boolean = false;

  public clientes: any = [];
  public usuarios: any = [];
  public asesores: any = [];
  public override filtros: any = {};
  
  modalRef!: BsModalRef;

  constructor(
    apiService: ApiService, 
    alertService: AlertService,
    private modalService: BsModalService
  ) {
    super(apiService, alertService);
  }

  protected getPaginatedData(): PaginatedResponse | null {
    return this.ordenes;
  }

  protected setPaginatedData(data: PaginatedResponse): void {
    this.ordenes = data;
  }

  ngOnInit() {
    this.loadAll();
    
    this.apiService.getAll('clientes/list')
      .pipe(this.untilDestroyed())
      .subscribe(clientes => {
        this.clientes = clientes;
      }, error => { this.alertService.error(error); });
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

  public loadAll() {
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
      .subscribe(ordenes => {
      this.ordenes = ordenes.data;
      this.loading = false;
      if (this.modalRef) {
        this.modalRef.hide();
      }
    }, error => { 
      this.alertService.error(error); 
      this.loading = false; 
    });
  }

  public setEstado(orden: any) {
    this.apiService.store('orden-produccion/cambiar-estado', orden)
      .pipe(this.untilDestroyed())
      .subscribe(
      response => {
        this.alertService.success('Orden actualizada', 'El estado de la orden fue actualizado exitosamente.');
      }, 
      error => {
        this.alertService.error(error);
      }
    );
  }

  changeStateOrden(ordenId: number, estado: string) {
    this.apiService.store('orden-produccion/cambiar-estado-orden', { id: ordenId, estado: estado })
      .pipe(this.untilDestroyed())
      .subscribe(
      data => {
        this.alertService.success('Orden actualizada', 'El estado de la orden fue actualizado exitosamente.');
        this.filtrarOrdenes();
      }, 
      error => {
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
      }
    );
  }

  // setPagination() ahora se hereda de BasePaginatedComponent

  public imprimir(orden: any) {
    window.open(this.apiService.baseUrl + '/api/orden-produccion/imprimir/' + orden.id + '?token=' + this.apiService.auth_token());
  }

  public openFilter(template: TemplateRef<any>) {
    if (!this.usuarios.length) {
      this.apiService.getAll('usuarios/list')
        .pipe(this.untilDestroyed())
        .subscribe(usuarios => {
          this.asesores = usuarios;
        }, error => { this.alertService.error(error); });
    }


    this.modalRef = this.modalService.show(template);
  }

  public descargar() {
    this.downloading = true;
    this.apiService.export('ordenes-produccion/exportar', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(
      (data: Blob) => {
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
      }, 
      error => { 
        this.alertService.error(error); 
        this.downloading = false; 
      }
    );
  }

  public anular(orden: any) {
    this.apiService.store('orden-produccion/anular', orden)
      .pipe(this.untilDestroyed())
      .subscribe(
      response => {
        this.alertService.success('Orden anulada', 'La orden fue anulada exitosamente.');
        orden.estado = 'anulada';

        this.filtrarOrdenes();
      }, 
      error => { this.alertService.error(error); }
    );
  }
}