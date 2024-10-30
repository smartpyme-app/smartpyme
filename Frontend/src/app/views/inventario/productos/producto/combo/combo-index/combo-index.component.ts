import { Component, OnInit, TemplateRef } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BsModalService } from 'ngx-bootstrap/modal';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-combo-index',
  templateUrl: './combo-index.component.html',
  styleUrls: ['./combo-index.component.css']
})
export class ComboIndexComponent implements OnInit {
  combos: any = [];
  downloading = false;
  usuarios: any = [];
  modalRef: any;
  filtros: any = {};
  bodegas: any = [];
  loading = false;

  constructor(public apiService: ApiService, public alertService: AlertService, private modalService: BsModalService) { }
  descargar() { }

  openFilter(template: TemplateRef<any>) {
    this.apiService.getAll('combos/list').subscribe(combos => {
      this.combos = combos;
    }, error => { this.alertService.error(error); });
    this.apiService.getAll('usuarios/list').subscribe(usuarios => {
      this.usuarios = usuarios;
    }, error => { this.alertService.error(error); });
    this.modalRef = this.modalService.show(template);


  }
  ngOnInit() {
    this.loadAll();

    this.apiService.getAll('bodegas/list').subscribe(bodegas => {
      this.bodegas = bodegas;
    }, error => { this.alertService.error(error); });

  }

  loadAll() {
    this.filtros.id_bodega = '';
    this.filtros.id_producto = '';
    this.filtros.id_usuario = '';
    this.filtros.estado = '';
    this.filtros.search = '';
    this.filtros.orden = 'created_at';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 10;

    this.loading = true;
    this.filtrar();
  }

  filtrar() {
    this.apiService.getAll('combos/index', this.filtros).subscribe(combos => {
      this.combos = combos;
      this.loading = false;
    }, error => { this.alertService.error(error); });
  }
  setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }

    this.loadAll();
  }
  async setComboState(combo: any) {
    //await 1 tick
    setTimeout(() => { }, 0);


    let res = await Swal.fire({
      title: 'Cambiar estado',
      text: '¿Está seguro de cambiar el estado del combo?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí',
      cancelButtonText: 'No'
    });
    if (!res.isConfirmed) {
      combo.estado = combo.estado == "Activo" ? "Inactivo" : "Activo";
      return;
    };

    this.apiService.store('combos/changeState', combo).subscribe((res: any) => {
      this.alertService.success("Cambio de estado exitoso", res.message);
      this.loadAll();
    }, error => { this.alertService.error(error); });
  }

}
