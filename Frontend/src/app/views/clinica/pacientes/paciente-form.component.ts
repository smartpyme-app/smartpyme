import { Component, DestroyRef, OnInit, TemplateRef, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-paciente-form',
  standalone: false,
  templateUrl: './paciente-form.component.html',
})
export class PacienteFormComponent implements OnInit {
  id: number | null = null;
  guardando = false;
  sucursales: any[] = [];
  especies: any[] = [];
  nuevaEspecie = '';
  nuevaRaza = '';
  modalRef!: BsModalRef;
  paciente: any = this.vacio();

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
    this.id = this.idDesdeRuta();
    const permiso = this.id ? 'clinica.pacientes.editar' : 'clinica.pacientes.crear';
    if (!this.apiService.hasPermission(permiso)) {
      this.router.navigate(['/']);
      return;
    }
    this.cargarSucursales();
    this.cargarEspecies(() => this.cargarPaciente());
  }

  get sexos(): string[] {
    return this.paciente.tipo === 'ANIMAL'
      ? ['macho', 'hembra', 'desconocido']
      : ['masculino', 'femenino', 'otro'];
  }

  get razasDeEspecie(): any[] {
    const especie = this.especies.find((item) => String(item.id) === String(this.paciente.id_especie));
    return especie?.razas ?? [];
  }

  cambiarTipo(): void {
    this.paciente.sexo = '';
    this.paciente.id_raza = '';
  }

  guardar(): void {
    this.guardando = true;
    const cuerpo = this.cuerpo();
    const solicitud = this.id
      ? this.apiService.update('clinica/pacientes', this.id, cuerpo)
      : this.apiService.store('clinica/pacientes', cuerpo);

    solicitud.pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.guardando = false;
        const id = respuesta?.data?.id ?? this.id;
        this.alertService.success('Listo', 'Paciente guardado. No se creó un cliente.');
        this.router.navigate(['/clinica/pacientes', id]);
      },
      error: (error) => {
        this.guardando = false;
        this.alertService.error(error);
      },
    });
  }

  abrirCatalogo(template: TemplateRef<any>): void {
    this.nuevaEspecie = '';
    this.nuevaRaza = '';
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  cerrarCatalogo(): void {
    this.modalRef?.hide();
    this.alertService.modal = false;
  }

  agregarEspecie(): void {
    const nombre = this.nuevaEspecie.trim();
    if (!nombre) {
      return;
    }
    this.apiService.store('clinica/especies', { nombre }).pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.nuevaEspecie = '';
        this.cargarEspecies(() => {
          this.paciente.id_especie = respuesta?.data?.id ?? '';
          this.paciente.id_raza = '';
        });
        this.cerrarCatalogo();
        this.alertService.success('Especie creada', 'La especie ha sido agregada.');
      },
      error: (error) => this.alertService.error(error),
    });
  }

  agregarRaza(): void {
    const nombre = this.nuevaRaza.trim();
    if (!nombre || !this.paciente.id_especie) {
      return;
    }
    this.apiService.store('clinica/especies/' + this.paciente.id_especie + '/razas', { nombre })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.nuevaRaza = '';
          this.cargarEspecies(() => {
            this.paciente.id_raza = respuesta?.data?.id ?? '';
          });
          this.cerrarCatalogo();
          this.alertService.success('Raza creada', 'La raza ha sido agregada.');
        },
        error: (error) => this.alertService.error(error),
      });
  }

  private idDesdeRuta(): number | null {
    const valor = this.route.snapshot.paramMap.get('id');
    return valor ? Number(valor) : null;
  }

  private cargarPaciente(): void {
    this.id = this.idDesdeRuta();
    if (!this.id) {
      const usuario = this.apiService.auth_user();
      this.paciente.id_sucursal = usuario?.id_sucursal ?? '';
      return;
    }
    this.apiService.get('clinica/pacientes/' + this.id).pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        const data = respuesta?.data;
        if (!data) {
          return;
        }
        this.paciente = {
          ...this.vacio(),
          tipo: data.tipo,
          nombres: data.nombres ?? '',
          apellidos: data.apellidos ?? '',
          nombre: data.nombre ?? '',
          fecha_nacimiento: data.fecha_nacimiento ?? '',
          sexo: data.sexo ?? '',
          documento: data.documento ?? '',
          telefono: data.telefono ?? '',
          correo: data.correo ?? '',
          direccion: data.direccion ?? '',
          informacion_relevante: data.informacion_relevante ?? '',
          id_sucursal: data.sucursal?.id ?? '',
          id_especie: data.especie?.id ?? '',
          id_raza: data.raza?.id ?? '',
          color: data.color ?? '',
          peso: data.peso ?? '',
          microchip: data.microchip ?? '',
          esterilizado: data.esterilizado === null || data.esterilizado === undefined ? '' : (data.esterilizado ? '1' : '0'),
          identificadores: data.identificadores ?? '',
        };
      },
      error: () => this.alertService.error('No se pudo cargar el paciente.'),
    });
  }

  private cargarSucursales(): void {
    this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe({
      next: (data) => {
        this.sucursales = Array.isArray(data) ? data : [];
      },
    });
  }

  private cargarEspecies(alTerminar?: () => void): void {
    this.apiService.getAll('clinica/especies').pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.especies = respuesta?.data ?? [];
        alTerminar?.();
      },
      error: () => alTerminar?.(),
    });
  }

  private cuerpo(): any {
    const esterilizado = this.paciente.esterilizado === '' ? null : this.paciente.esterilizado === '1';
    return {
      ...this.paciente,
      esterilizado,
      id_sucursal: this.paciente.id_sucursal || null,
      id_especie: this.paciente.id_especie || null,
      id_raza: this.paciente.id_raza || null,
      peso: this.paciente.peso === '' ? null : this.paciente.peso,
    };
  }

  private vacio(): any {
    return {
      tipo: 'HUMANO',
      nombres: '',
      apellidos: '',
      nombre: '',
      fecha_nacimiento: '',
      sexo: '',
      documento: '',
      telefono: '',
      correo: '',
      direccion: '',
      informacion_relevante: '',
      id_sucursal: '',
      id_especie: '',
      id_raza: '',
      color: '',
      peso: '',
      microchip: '',
      esterilizado: '',
      identificadores: '',
    };
  }
}
