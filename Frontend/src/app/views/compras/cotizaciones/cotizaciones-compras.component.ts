import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';
import Swal from 'sweetalert2';


@Component({
    selector: 'app-cotizaciones-compras',
    templateUrl: './cotizaciones-compras.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent],
    
})

export class CotizacionesComprasComponent extends BasePaginatedComponent implements OnInit {

  public compras: PaginatedResponse<any> = {} as PaginatedResponse;
  public compra: any = {};
  public downloading: boolean = false;

  public proveedores: any = [];
  public usuarios: any = [];
  public sucursales: any = [];
  public documentos: any = [];
  public override filtros: any = {};
  public filtrado: boolean = false;

  modalRef!: BsModalRef;
  comprasOriginal: any;

  constructor(apiService: ApiService, alertService: AlertService,
    private modalService: BsModalService
  ) {
    super(apiService, alertService);
  }

  protected getPaginatedData(): PaginatedResponse | null {
    return this.compras;
  }

  protected setPaginatedData(data: PaginatedResponse): void {
    this.compras = data;
  }

  ngOnInit() {

    this.loadAll();

    this.apiService.getAll('proveedores/list').subscribe(proveedores => {
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

  public loadAll() {
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
    this.apiService.getAll('ordenes-de-compras', this.filtros).subscribe(compras => {
      this.compras = compras;
      this.loading = false;

      this.comprasOriginal = Object.assign({}, compras);
      if (this.modalRef) {
        this.modalRef.hide();
      }
    }, error => { this.alertService.error(error); });
  }

  async setEstado(cotizacion: any) {


    let Index = this.compras["data"].findIndex((x: any) => x.id == cotizacion.id);
    let currentState = this.comprasOriginal["data"][Index].estado;
    let confirm = await Swal.fire({
      title: '¿Estás seguro?',
      text: 'Se acambiará el estado de la orden de compra a ' + cotizacion.estado,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cambiarlo',
      cancelButtonText: 'Cancelar'
    });
    if (!confirm.isConfirmed) {
      this.compras["data"][Index].estado = currentState;
      return;
    }


    this.apiService.store('orden-de-compra', cotizacion).subscribe(cotizacion => {
      this.alertService.success('Orden de compra actualizada', 'La orden de compra fue actualizada exitosamente.');
    }, error => {
      this.alertService.error(error);
      this.compras["data"][Index].estado = error.error.currentState;

    });
  }



  public delete(id: number) {
    if (confirm('¿Desea eliminar el Registro?')) {
      this.apiService.delete('orden-de-compra/', id).subscribe(data => {
        for (let i = 0; i < this.compras['data'].length; i++) {
          if (this.compras['data'][i].id == data.id)
            this.compras['data'].splice(i, 1);
        }
      }, error => { this.alertService.error(error); });

    }

  }

  // setPagination() ahora se hereda de BasePaginatedComponent

  public reemprimir(compra: any) {
    window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + compra.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
  }

  // Editar

  openModalEdit(template: TemplateRef<any>, compra: any) {
    this.compra = compra;

    this.apiService.getAll('documentos').subscribe(documentos => {
      this.documentos = documentos;
    }, error => { this.alertService.error(error); });

    this.modalRef = this.modalService.show(template);
  }

  public onSubmit() {
    this.loading = true;
    this.apiService.store('orden-de-compra', this.compra).subscribe(compra => {
      this.compra = {};
      this.modalRef.hide();
      this.loading = false;
      this.alertService.success('Orden de compra guardada', 'La orden de compra fue guardada exitosamente.');
    }, error => { this.alertService.error(error); this.loading = false; });

  }

  public openFilter(template: TemplateRef<any>) {
    this.apiService.getAll('sucursales/list').subscribe(sucursales => {
      this.sucursales = sucursales;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('usuarios/list').subscribe(usuarios => {
      this.usuarios = usuarios;
    }, error => { this.alertService.error(error); });

    this.modalRef = this.modalService.show(template);
  }

  public imprimir(compra: any) {
    window.open(this.apiService.baseUrl + '/api/orden-de-compra/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
  }

  public descargar() {
    this.downloading = true;
    this.apiService.export('ordenes-de-compras/exportar', this.filtros).subscribe((data: Blob) => {
      const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'ordenes-de-compras.xlsx';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      this.downloading = false;
    }, (error) => { this.alertService.error(error); this.downloading = false; }
    );
  }


}
