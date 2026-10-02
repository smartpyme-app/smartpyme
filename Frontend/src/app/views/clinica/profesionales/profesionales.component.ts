import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-profesionales',
  standalone: false,
  templateUrl: './profesionales.component.html',
})
export class ProfesionalesComponent implements OnInit {
  profesionales: any[] = [];
  candidatos: any[] = [];
  sucursales: any[] = [];
  loading = false;
  estado = '1';
  editando = false;
  modalRef!: BsModalRef;
  form: any = this.formVacio();

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private modalService: BsModalService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.profesionales.ver')) {
      this.router.navigate(['/']);
      return;
    }
    this.cargarSucursales();
    this.filtrar();
  }

  filtrar(): void {
    this.loading = true;
    this.apiService.getAll('clinica/profesionales', { estado: this.estado }).subscribe({
      next: (respuesta) => {
        this.profesionales = respuesta?.data ?? [];
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      },
    });
  }

  abrir(template: TemplateRef<any>, profesional?: any): void {
    this.editando = !!profesional;
    this.form = profesional
      ? {
          id_usuario: profesional.id_usuario,
          cargo: profesional.cargo ?? '',
          especialidad: profesional.especialidad ?? '',
          colegiatura: profesional.colegiatura ?? '',
          sucursales: (profesional.sucursales ?? []).map((item: any) => item.id),
        }
      : this.formVacio();
    if (!this.candidatos.length) {
      this.apiService.getAll('clinica/profesionales/candidatos').subscribe({
        next: (respuesta) => {
          this.candidatos = respuesta?.data ?? [];
        },
        error: (error) => this.alertService.error(error),
      });
    }
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  cerrar(): void {
    this.modalRef?.hide();
    this.alertService.modal = false;
  }

  guardar(): void {
    this.apiService.store('clinica/profesionales', this.form).subscribe({
      next: () => {
        this.cerrar();
        this.alertService.success('Listo', 'Profesional habilitado.');
        this.filtrar();
      },
      error: (error) => this.alertService.error(error),
    });
  }

  desactivar(profesional: any): void {
    this.apiService.patch('clinica/profesionales', profesional.id + '/estado', {}).subscribe({
      next: () => {
        this.alertService.success('Listo', 'Profesional deshabilitado. Las atenciones anteriores conservan su nombre.');
        this.filtrar();
      },
      error: (error) => this.alertService.error(error),
    });
  }

  sucursalesDe(profesional: any): string {
    return (profesional.sucursales ?? []).map((item: any) => item.nombre).join(', ') || '—';
  }

  marcada(id: number): boolean {
    return (this.form.sucursales ?? []).map(String).includes(String(id));
  }

  alternarSucursal(id: number, marcado: boolean): void {
    const actuales = new Set((this.form.sucursales ?? []).map(Number));
    if (marcado) {
      actuales.add(Number(id));
    } else {
      actuales.delete(Number(id));
    }
    this.form.sucursales = Array.from(actuales);
  }

  private cargarSucursales(): void {
    this.apiService.getAll('sucursales/list').subscribe({
      next: (data) => {
        this.sucursales = Array.isArray(data) ? data : [];
      },
    });
  }

  private formVacio(): any {
    return {
      id_usuario: '',
      cargo: '',
      especialidad: '',
      colegiatura: '',
      sucursales: [],
    };
  }
}
