import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-pacientes',
  standalone: false,
  templateUrl: './pacientes.component.html',
})
export class PacientesComponent implements OnInit {
  pacientes: any = {};
  sucursales: any[] = [];
  especies: any[] = [];
  loading = false;
  filtros: any = {
    buscador: '',
    tipo: '',
    estado: '1',
    id_sucursal: '',
    id_especie: '',
    paginate: 25,
    page: 1,
  };
  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private modalService: BsModalService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.pacientes.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.cargarSucursales();
    this.cargarEspecies();
    this.filtrar(false);
  }

  filtrar(resetPage = true): void {
    if (resetPage) {
      this.filtros.page = 1;
    }
    this.loading = true;
    this.apiService.getAll('clinica/pacientes', this.filtros).subscribe({
      next: (respuesta) => {
        this.pacientes = respuesta ?? {};
        this.loading = false;
        this.modalRef?.hide();
        this.alertService.modal = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      },
    });
  }

  setPagination(event: { page: number }): void {
    this.filtros.page = event.page;
    this.filtrar(false);
  }

  openModal(template: TemplateRef<any>): void {
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template);
  }

  cambiarEstado(paciente: any, activo: boolean): void {
    this.apiService.patch('clinica/pacientes', paciente.id + '/estado', { activo }).subscribe({
      next: () => {
        this.alertService.success('Listo', activo ? 'Paciente habilitado.' : 'Paciente deshabilitado.');
        this.filtrar(false);
      },
      error: (error) => this.alertService.error(error),
    });
  }

  private cargarSucursales(): void {
    this.apiService.getAll('sucursales/list').subscribe({
      next: (data) => {
        this.sucursales = Array.isArray(data) ? data : [];
      },
    });
  }

  private cargarEspecies(): void {
    this.apiService.getAll('clinica/especies').subscribe({
      next: (respuesta) => {
        this.especies = respuesta?.data ?? [];
      },
    });
  }
}
