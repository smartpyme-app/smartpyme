import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';


@Component({
    selector: 'app-cotizaciones',
    templateUrl: './cotizaciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent],
    
})

export class CotizacionesComponent implements OnInit {

  public ventas: any = [];
  public venta: any = {};
  public loading: boolean = false;
  public downloading: boolean = false;

  public clientes: any = [];
  public usuarios: any = [];
  public canales: any = [];
  public proyectos: any = [];
  public formaPagos: any = [];
  public sucursales: any = [];
  public documentos: any = [];
  public filtros: any = {};
  public filtrado: boolean = false;

  modalRef!: BsModalRef;

  constructor(public apiService: ApiService, private alertService: AlertService,
    private modalService: BsModalService
  ) { }

  ngOnInit() {

    this.loadAll();

    this.apiService.getAll('clientes/list').subscribe(clientes => {
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

    this.filtrarVentas();
  }

  public loadAll() {
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
    if (!this.filtros.id_cliente) this.filtros.id_cliente = '';
  
    this.apiService.getAll('cotizaciones', this.filtros).subscribe(ventas => {
      this.ventas = this.normalizeVentas(ventas);
      this.loading = false;
      if (this.modalRef) this.modalRef.hide();
    }, error => { this.alertService.error(error); this.loading = false; });
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

  // public setEstado(cotizacion: any) {
  //   this.apiService.store('updateStateCotizacionVentas', cotizacion).subscribe(cotizacion => {
  //     this.alertService.success('Cotización actualizada', 'La cotización fue actualizada exitosamente.');
  //   }, error => { this.alertService.error(error); });
  // }

public setEstado(cotizacion: any) {
  // Agregamos el distintivo
  cotizacion.cotizacion_id = 1;
  
  this.apiService.store('cotizacion', cotizacion).subscribe(
    response => {
      this.alertService.success('Cotización actualizada', 'La cotización fue actualizada exitosamente.');
    }, 
    error => {
      this.alertService.error(error);
    }
  );
 }


  public delete(id: number) {
    if (confirm('¿Desea eliminar el Registro?')) {
      this.apiService.delete('venta/', id).subscribe(data => {
        for (let i = 0; i < this.ventas['data'].length; i++) {
          if (this.ventas['data'][i].id == data.id)
            this.ventas['data'].splice(i, 1);
        }
      }, error => { this.alertService.error(error); });

    }

  }


  public setPagination(event: any): void {
    this.loading = true;
    this.apiService.paginate(this.ventas.path + '?page=' + event.page, this.filtros).subscribe(ventas => {
      this.ventas = this.normalizeVentas(ventas);
      this.loading = false;
    }, error => { this.alertService.error(error); this.loading = false; });
  }

  public reemprimir(venta: any) {
    window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
  }

  // Editar

  openModalEdit(template: TemplateRef<any>, venta: any) {
    this.venta = venta;

    this.apiService.getAll('documentos').subscribe(documentos => {
      this.documentos = documentos;
    }, error => { this.alertService.error(error); });

    this.modalRef = this.modalService.show(template);
  }

  public onSubmit() {
    this.loading = true;
    this.apiService.store('cotizacion', this.venta).subscribe(venta => {
      this.venta = {};
      this.modalRef.hide();
      this.loading = false;
      this.alertService.success('Cotización guardado', 'La cotización fue guardado exitosamente.');
    }, error => { this.alertService.error(error); this.loading = false; });

  }

  public openFilter(template: TemplateRef<any>) {
    if (!this.sucursales.length) {
      this.apiService.getAll('sucursales/list').subscribe(sucursales => {
        this.sucursales = sucursales;
      }, error => { this.alertService.error(error); });
    }

    if (!this.usuarios.length) {
      this.apiService.getAll('usuarios/list').subscribe(usuarios => {
        this.usuarios = usuarios;
      }, error => { this.alertService.error(error); });
    }

    if (!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos) {
      this.apiService.getAll('proyectos/list').subscribe(proyectos => {
        this.proyectos = proyectos;
      }, error => { this.alertService.error(error); });
    }

    this.modalRef = this.modalService.show(template);
  }

  public imprimir(venta: any) {
   
    window.open(this.apiService.baseUrl + '/api/cotizacion/impresion/' + venta.id + '/cotizacion?token=' + this.apiService.auth_token());
   // window.open(this.apiService.baseUrl + '/api/cotizacion/impresion/' + venta.id + '?token=' + this.apiService.auth_token() + '&tipo=' + tipo);
  }

  public descargar() {
    this.downloading = true;
    //agregar a filtros que es una cotizacion_id
    this.filtros.cotizacion_id = 1;
    this.apiService.export('cotizaciones/exportar', this.filtros).subscribe((data: Blob) => {
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
    this.apiService.store('cotizacion/changeState', { id: ventaId, estado: estado }).subscribe(data => {
        this.alertService.success(`Cotización ${estado}`, `La cotización fue ${estado} exitosamente.`);
      this.filtrarVentas();
    }, error => { this.alertService.error(error); });
  }

  public duplicarCotizacion(id: number) {
    this.apiService.store('cotizacion/duplicar', { id: id }).subscribe(data => {
      this.alertService.success('Cotización duplicada', 'La cotización fue duplicada exitosamente.');
      this.filtrarVentas();
    }, error => { this.alertService.error(error); });
  }

}
