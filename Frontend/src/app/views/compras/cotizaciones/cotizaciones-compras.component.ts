import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import Swal from 'sweetalert2';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-cotizaciones-compras',
    templateUrl: './cotizaciones-compras.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, LazyImageDirective],
    
})

export class CotizacionesComprasComponent extends BaseCrudComponent<any> implements OnInit {

  public compras:any = {};
  public compra: any = {};
  public downloading: boolean = false;
  public proveedores: any = [];
  public usuarios: any = [];
  public sucursales: any = [];
  public documentos: any = [];
  public filtrado: boolean = false;
  public comprasOriginal: any;

  constructor(
    apiService: ApiService, 
    alertService: AlertService,
    modalManager: ModalManagerService
  ) {
    super(apiService, alertService, modalManager, {
      endpoint: 'orden-de-compra',
      itemsProperty: 'compras',
      itemProperty: 'compra',
      reloadAfterSave: false,
      reloadAfterDelete: false,
      messages: {
        created: 'La orden de compra fue guardada exitosamente.',
        updated: 'La orden de compra fue guardada exitosamente.',
        createTitle: 'Orden de compra guardada',
        updateTitle: 'Orden de compra guardada'
      },
      afterSave: () => {
        this.compra = {};
      }
    });
  }

  protected aplicarFiltros(): void {
    this.filtrarCompras();
  }

  ngOnInit() {
    this.loadAll();

    this.apiService.getAll('proveedores/list')
        .pipe(this.untilDestroyed())
        .subscribe(proveedores => {
            this.proveedores = proveedores;
        }, error => { this.alertService.error(error); });
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }

    this.filtrarCompras();
  }

  public override loadAll() {
    this.filtros.id_sucursal = '';
    this.filtros.id_proveedor = '';
    this.filtros.id_usuario = '';
    this.filtros.estado = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'fecha';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 10;
    this.filtrarCompras();
  }

  public filtrarCompras() {
    this.loading = true;
    if (!this.filtros.id_proveedor) {
      this.filtros.id_proveedor = '';
    }
    this.apiService.getAll('ordenes-de-compras', this.filtros)
        .pipe(this.untilDestroyed())
        .subscribe(compras => {
            this.compras = compras;
            this.loading = false;
            this.comprasOriginal = Object.assign({}, compras);
            this.closeModal();
        }, error => { this.alertService.error(error); this.loading = false; });
  }

  async setEstado(cotizacion: any) {
    let Index = this.compras.data?.findIndex((x: any) => x.id == cotizacion.id);
    let currentState = this.comprasOriginal?.data?.[Index]?.estado;
    
    let confirm = await Swal.fire({
      title: '¿Estás seguro?',
      text: 'Se cambiará el estado de la orden de compra a ' + cotizacion.estado,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cambiarlo',
      cancelButtonText: 'Cancelar'
    });
    
    if (!confirm.isConfirmed) {
      if (Index !== -1 && Index !== undefined && this.compras.data) {
        this.compras.data[Index].estado = currentState;
      }
      return;
    }

    try {
      await this.apiService.store('orden-de-compra', cotizacion)
          .pipe(this.untilDestroyed())
          .toPromise();
      
      this.alertService.success('Orden de compra actualizada', 'La orden de compra fue actualizada exitosamente.');
    } catch (error: any) {
      this.alertService.error(error);
      if (Index !== -1 && Index !== undefined && this.compras.data && error.error?.currentState) {
        this.compras.data[Index].estado = error.error.currentState;
      }
    }
  }

  public override async delete(item: any | number): Promise<void> {
    const itemToDelete = typeof item === 'number' ? item : (item as any).id;
    
    if (!confirm('¿Desea eliminar el Registro?')) {
      return;
    }

    this.loading = true;
    try {
      const deletedItem = await this.apiService.delete('orden-de-compra/', itemToDelete)
          .pipe(this.untilDestroyed())
          .toPromise();
      
      const index = this.compras.data?.findIndex((c: any) => c.id === deletedItem.id);
      if (index !== -1 && index >= 0) {
        this.compras.data.splice(index, 1);
      }
      this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
    } catch (error: any) {
      this.alertService.error(error);
    } finally {
      this.loading = false;
    }
  }

  public reemprimir(compra:any){
    window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + compra.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
  }

  openModalEdit(template: TemplateRef<any>, compra:any) {
    this.compra = compra;
    
    this.apiService.getAll('documentos')
        .pipe(this.untilDestroyed())
        .subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

    this.openModal(template, compra);
  }

  public openFilter(template: TemplateRef<any>) {
    this.apiService.getAll('sucursales/list')
        .pipe(this.untilDestroyed())
        .subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

    this.apiService.getAll('usuarios/list')
        .pipe(this.untilDestroyed())
        .subscribe(usuarios => { 
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error); });

    this.openModal(template);
  }

  public imprimir(compra:any){
    window.open(this.apiService.baseUrl + '/api/orden-de-compra/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
  }

  public descargar(){
    this.downloading = true;
    this.apiService.export('ordenes-de-compras/exportar', this.filtros)
        .pipe(this.untilDestroyed())
        .subscribe((data:Blob) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'cotizaciones-compras.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      }, (error) => { this.alertService.error(error); this.downloading = false; }
    );
  }

}
