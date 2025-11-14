import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { Router } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-retaceos-list',
    templateUrl: './retaceos-list.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, PopoverModule, TooltipModule],
    
})
export class RetaceosListComponent extends BaseModalComponent implements OnInit {

  public retaceos: any = [];
  public override loading = false;

  public filtros: any = {};
  public clientes: any = [];
  public usuarios: any = [];
  public sucursales: any = [];

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private router: Router
  ) {
    super(modalManager, alertService);
  }

  ngOnInit() {
    this.loadAll();
  }

  public loadAll() {

    const filtrosGuardados = localStorage.getItem('retaceosFiltros');

    if (filtrosGuardados) {
      this.filtros = JSON.parse(filtrosGuardados);
      // console.log(this.filtros);
    } else {

    const hoy = new Date();
    const mesAnterior = new Date();
    mesAnterior.setMonth(hoy.getMonth() - 1);
    this.filtros.id_sucursal = '';
  //  this.filtros.id_cliente = '';
    this.filtros.id_usuario = '';
    this.filtros.inicio = this.formatDate(mesAnterior);
    this.filtros.fin = this.formatDate(hoy);
    this.filtros.busqueda = '';
    this.filtros.paginate = 10;
    this.filtros.estado = '';
    this.filtros.orden = 'fecha';
    this.filtros.direccion = 'desc';
    }

    this.cargarRetaceos();
  }



  formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  cargarRetaceos() {
    localStorage.setItem('retaceosFiltros', JSON.stringify(this.filtros));
    this.loading = true;

    this.apiService.getAll('retaceos', this.filtros).subscribe(response => {
      this.retaceos = response
      this.loading = false;
      this.closeModal();
    }, error => {
      this.alertService.error(error);
      this.loading = false;
    });
  }


  verRetaceo(retaceo: any) {
    this.router.navigate(['/retaceo', retaceo.id]);
  }

  eliminarRetaceo(id: number) {
    Swal.fire({
      title: '¿Está seguro?',
      text: 'Esta acción no se puede revertir. Los costos de los productos volverán a su valor original.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then(result => {
      if (result.isConfirmed) {
        this.apiService.delete('retaceo/', id).subscribe(() => {
          this.alertService.success('Retaceo eliminado correctamente', 'Retaceo eliminado');
          this.cargarRetaceos();
        }, error => {
          this.alertService.error(error);
        });
      }
    });
  }

  filtrarRetaceos() {
    this.cargarRetaceos();
  }
  openFilter(template: TemplateRef<any>) {
    if(!this.clientes.length){
      this.apiService.getAll('clientes/list').subscribe(clientes => {
          this.clientes = clientes;
      }, error => {this.alertService.error(error); });
    }
    if(!this.usuarios.length){
      this.apiService.getAll('usuarios/list').subscribe(usuarios => {
          this.usuarios = usuarios;
      }, error => {this.alertService.error(error); });
    }
    if(!this.sucursales.length){
      this.apiService.getAll('sucursales/list').subscribe(sucursales => {
        this.sucursales = sucursales;
      }, error => {this.alertService.error(error); });
    }
    this.openModal(template);
  }

  public limpiarFiltros() {
    localStorage.removeItem('retaceosFiltros');
    this.loadAll();
  }
  generarPartidaContable(retaceo: any) {
    this.loading = true;

    this.apiService.store('contabilidad/partida/retaceo', { id_retaceo: retaceo.id })
      .subscribe(
        (response) => {
          this.alertService.success(
            `Partidas contables generadas correctamente. Se crearon ${response.partidas_creadas} partidas.`,
            'Partidas generadas'
          );
          // Marcar el retaceo como contabilizado
          retaceo.contabilizado = true;
          this.loading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

}
