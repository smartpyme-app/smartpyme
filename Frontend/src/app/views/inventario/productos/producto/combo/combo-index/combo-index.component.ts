import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-combo-index',
    templateUrl: './combo-index.component.html',
    styleUrls: ['./combo-index.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class ComboIndexComponent extends BaseModalComponent implements OnInit {
  combos: any = [];
  downloading = false;
  usuarios: any = [];
  filtros: any = {};
  bodegas: any = [];

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService, 
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService
  ) {
    super(modalManager, alertService);
  }
  descargar() { }

  openFilter(template: TemplateRef<any>) {
    this.apiService.getAll('combos/list')
      .pipe(this.untilDestroyed())
      .subscribe(combos => {
      this.combos = combos;
    }, error => { this.alertService.error(error); });
    this.apiService.getAll('usuarios/list')
      .pipe(this.untilDestroyed())
      .subscribe(usuarios => {
      this.usuarios = usuarios;
    }, error => { this.alertService.error(error); });
    this.openModal(template);


  }
  ngOnInit() {
    this.loadAll();

    this.apiService.getAll('bodegas/list')
      .pipe(this.untilDestroyed())
      .subscribe(bodegas => {
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
    this.apiService.getAll('combos/index', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(combos => {
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

    this.apiService.store('combos/changeState', combo)
      .pipe(this.untilDestroyed())
      .subscribe((res: any) => {
      this.alertService.success("Cambio de estado exitoso", res.message);
      this.loadAll();
    }, error => { this.alertService.error(error); });
  }

}
