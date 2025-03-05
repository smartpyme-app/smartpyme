import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cotizaciones-compras',
  templateUrl: './cotizaciones-compras.component.html',
})
export class CotizacionesComprasComponent implements OnInit {
  public compras: any = [];
  public compra: any = {};
  public loading: boolean = false;
  public downloading: boolean = false;

  public proveedores: any = [];
  public usuarios: any = [];
  public sucursales: any = [];
  public documentos: any = [];
  public filtros: any = {};
  public filtrado: boolean = false;

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.loadAll();

    this.apiService.getAll('proveedores/list').subscribe(
      (proveedores) => {
        this.proveedores = proveedores;
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }

    this.filtrarCompras();
  }

  public loadAll() {
    const filtrosGuardados = localStorage.getItem('cotizacionesComprasFiltros');

    if (filtrosGuardados) {
      this.filtros = JSON.parse(filtrosGuardados);
    } else {
      this.filtros.id_sucursal = '';
      this.filtros.id_proveedor = '';
      this.filtros.id_usuario = '';
      this.filtros.estado = '';
      this.filtros.buscador = '';
      this.filtros.orden = 'fecha';
      this.filtros.direccion = 'desc';
      this.filtros.paginate = 10;
    }

    this.filtrarCompras();
  }

  public filtrarCompras() {
    localStorage.setItem('cotizacionesComprasFiltros', JSON.stringify(this.filtros));
    this.loading = true;
    if (!this.filtros.id_proveedor) {
      this.filtros.id_proveedor = '';
    }
    this.apiService.getAll('ordenes-de-compras', this.filtros).subscribe(
      (compras) => {
        this.compras = compras;
        this.loading = false;
        if (this.modalRef) {
          this.modalRef.hide();
        }
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public setEstado(cotizacion: any) {
    this.apiService.store('orden-de-compra', cotizacion).subscribe(
      (cotizacion) => {
        this.alertService.success(
          'Orden de compra actualizada',
          'La orden de compra fue actualizada exitosamente.'
        );
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public delete(id: number) {
    if (confirm('¿Desea eliminar el Registro?')) {
      this.apiService.delete('orden-de-compra/', id).subscribe(
        (data) => {
          for (let i = 0; i < this.compras['data'].length; i++) {
            if (this.compras['data'][i].id == data.id)
              this.compras['data'].splice(i, 1);
          }
        },
        (error) => {
          this.alertService.error(error);
        }
      );
    }
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService
      .paginate(this.compras.path + '?page=' + event.page, this.filtros)
      .subscribe(
        (compras) => {
          this.compras = compras;
          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

  public reemprimir(compra: any) {
    window.open(
      this.apiService.baseUrl +
        '/api/reporte/facturacion/' +
        compra.id +
        '?token=' +
        this.apiService.auth_token(),
      'Impresión',
      'width=400'
    );
  }

  // Editar

  openModalEdit(template: TemplateRef<any>, compra: any) {
    this.compra = compra;

    this.apiService.getAll('documentos').subscribe(
      (documentos) => {
        this.documentos = documentos;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.modalRef = this.modalService.show(template);
  }

  public onSubmit() {
    this.loading = true;
    this.apiService.store('orden-de-compra', this.compra).subscribe(
      (compra) => {
        this.compra = {};
        this.modalRef.hide();
        this.loading = false;
        this.alertService.success(
          'Orden de compra guardada',
          'La orden de compra fue guardada exitosamente.'
        );
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public openFilter(template: TemplateRef<any>) {
    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('usuarios/list').subscribe(
      (usuarios) => {
        this.usuarios = usuarios;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.modalRef = this.modalService.show(template);
  }

  public imprimir(compra: any) {
    window.open(
      this.apiService.baseUrl +
        '/api/orden-de-compra/impresion/' +
        compra.id +
        '?token=' +
        this.apiService.auth_token()
    );
  }

  public descargar() {
    this.downloading = true;
    this.apiService
      .export('ordenes-de-compras/exportar', this.filtros)
      .subscribe(
        (data: Blob) => {
          const blob = new Blob([data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'ordenes-de-compras.xlsx';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
          this.downloading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.downloading = false;
        }
      );
  }

  public limpiarFiltros() {
    localStorage.removeItem('cotizacionesComprasFiltros');
    this.loadAll();
  }
}
