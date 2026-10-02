import { Component, DestroyRef, OnInit, TemplateRef, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-paciente-ficha',
  standalone: false,
  templateUrl: './paciente-ficha.component.html',
})
export class PacienteFichaComponent implements OnInit {
  paciente: any = null;
  cargando = true;
  modalRef!: BsModalRef;
  clientesEncontrados: any[] = [];
  editandoId: number | null = null;
  form: any = this.formVacio();

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private modalService: BsModalService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    if (!this.apiService.hasPermission('clinica.pacientes.ver')) {
      this.router.navigate(['/']);
      return;
    }
    const id = this.route.snapshot.paramMap.get('id');
    this.apiService.get('clinica/pacientes/' + id).pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.paciente = respuesta?.data ?? null;
        this.cargando = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.cargando = false;
      },
    });
  }

  cambiarEstado(activo: boolean): void {
    if (!this.paciente) {
      return;
    }
    this.apiService.patch('clinica/pacientes', this.paciente.id + '/estado', { activo })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.paciente = respuesta?.data ?? this.paciente;
          this.alertService.success('Listo', activo ? 'Paciente reactivado.' : 'Paciente desactivado. El expediente se conserva.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  get responsablesVigentes(): any[] {
    return (this.paciente?.responsables ?? []).filter((item: any) => item.vigente);
  }

  get responsablesAnteriores(): any[] {
    return (this.paciente?.responsables ?? []).filter((item: any) => !item.vigente);
  }

  etiquetaRol(rol: string): string {
    const etiquetas: Record<string, string> = {
      principal: 'Principal',
      secundario: 'Secundario',
      tutor: 'Tutor',
      contacto_emergencia: 'Contacto de emergencia',
    };
    return etiquetas[rol] ?? rol;
  }

  cerrarAlta(cerrada: boolean): void {
    if (!this.paciente) {
      return;
    }
    this.apiService.patch('clinica/pacientes', this.paciente.id + '/alta', { cerrada })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.paciente = respuesta?.data ?? this.paciente;
          this.alertService.success('Listo', cerrada ? 'Alta cerrada.' : 'Alta reabierta.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  abrirResponsable(template: TemplateRef<any>, vinculo?: any): void {
    this.editandoId = vinculo?.id ?? null;
    this.clientesEncontrados = [];
    this.form = vinculo
      ? { ...this.formVacio(), rol: vinculo.rol, es_principal: vinculo.es_principal, nombre: vinculo.nombre }
      : this.formVacio();
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  cerrarModal(): void {
    this.modalRef?.hide();
    this.alertService.modal = false;
  }

  buscarClientes(): void {
    const q = (this.form.busqueda || '').trim();
    if (q.length < 2) {
      return;
    }
    this.apiService.getAll('clientes/search', { q }).pipe(this.untilDestroyed()).subscribe({
      next: (lista) => {
        this.clientesEncontrados = Array.isArray(lista) ? lista : [];
      },
      error: (error) => this.alertService.error(error),
    });
  }

  guardarResponsable(): void {
    if (!this.paciente) {
      return;
    }
    const cuerpo: any = {
      rol: this.form.rol,
      es_principal: this.form.rol === 'principal' || this.form.es_principal,
    };
    if (!this.editandoId) {
      if (this.form.modo === 'paciente') {
        cuerpo.es_el_paciente = true;
      } else if (this.form.modo === 'cliente') {
        cuerpo.id_cliente = this.form.id_cliente;
      } else {
        cuerpo.nombre = this.form.nombre;
        cuerpo.documento = this.form.documento;
        cuerpo.telefono = this.form.telefono;
        cuerpo.correo = this.form.correo;
      }
    }

    const solicitud = this.editandoId
      ? this.apiService.update('clinica/pacientes/' + this.paciente.id + '/responsables', this.editandoId, cuerpo)
      : this.apiService.store('clinica/pacientes/' + this.paciente.id + '/responsables', cuerpo);

    solicitud.pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.paciente = respuesta?.data ?? this.paciente;
        this.cerrarModal();
        this.alertService.success('Listo', 'Responsable guardado. El paciente no se convirtió en cliente.');
      },
      error: (error) => this.alertService.error(error),
    });
  }

  desactivarResponsable(vinculo: any): void {
    if (!this.paciente) {
      return;
    }
    this.apiService.patch('clinica/pacientes', this.paciente.id + '/responsables/' + vinculo.id, {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.paciente = respuesta?.data ?? this.paciente;
          this.alertService.success('Listo', 'Vínculo desactivado. El paciente y el cliente se conservan.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  nombreCliente(cliente: any): string {
    return cliente?.tipo === 'Empresa'
      ? (cliente.nombre_empresa || cliente.nombre)
      : `${cliente?.nombre || ''} ${cliente?.apellido || ''}`.trim();
  }

  private formVacio(): any {
    return {
      modo: 'cliente',
      id_cliente: '',
      nombre: '',
      documento: '',
      telefono: '',
      correo: '',
      rol: 'principal',
      es_principal: true,
      busqueda: '',
    };
  }
}
