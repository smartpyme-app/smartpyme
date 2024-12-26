import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-ordenes-produccion',
  templateUrl: './ordenes-produccion.component.html'
})
export class OrdenesProduccionComponent implements OnInit {
  public ordenes: any = [];
  public orden: any = {};
  public loading: boolean = false;
  public downloading: boolean = false;

  public clientes: any = [];
  public usuarios: any = [];
  public asesores: any = [];
  public filtros: any = {};
  
  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService, 
    private alertService: AlertService,
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
    this.apiService.getAll('ordenes-produccion', this.filtros).subscribe(ordenes => {
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
    this.apiService.store('orden-produccion/cambiar-estado', orden).subscribe(
      response => {
        this.alertService.success('Orden actualizada', 'El estado de la orden fue actualizado exitosamente.');
      }, 
      error => {
        this.alertService.error(error);
      }
    );
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService.paginate(this.ordenes.path + '?page=' + event.page, this.filtros).subscribe(ordenes => {
      this.ordenes = ordenes;
      this.loading = false;
    }, error => { 
      this.alertService.error(error); 
      this.loading = false; 
    });
  }

  public imprimir(orden: any) {
    window.open(this.apiService.baseUrl + '/api/orden-produccion/imprimir/' + orden.id + '?token=' + this.apiService.auth_token());
  }

  public openFilter(template: TemplateRef<any>) {
    if (!this.usuarios.length) {
      this.apiService.getAll('usuarios/list').subscribe(usuarios => {
        this.asesores = usuarios;
      }, error => { this.alertService.error(error); });
    }


    this.modalRef = this.modalService.show(template);
  }

  public descargar() {
    this.downloading = true;
    this.apiService.export('ordenes-produccion/exportar', this.filtros).subscribe(
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
    this.apiService.delete('orden-produccion', orden.id).subscribe(
      response => {
        this.alertService.success('Orden anulada', 'La orden fue anulada exitosamente.');
      }, 
      error => { this.alertService.error(error); }
    );
  }
}