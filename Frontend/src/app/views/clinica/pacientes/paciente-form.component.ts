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
  responsables: any[] = [];
  clientesEncontrados: any[] = [];
  editandoResponsable: number | null = null;
  responsable: any = this.responsableVacio();
  private siguienteLocal = -1;
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
    if (this.paciente.tipo === 'ANIMAL') {
      this.responsables = this.responsables.filter((item) => !item.local || !item.es_el_paciente);
    }
  }

  get responsablesVigentes(): any[] {
    return this.responsables.filter((item) => item.vigente !== false);
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

  guardar(): void {
    this.guardando = true;
    const cuerpo = this.cuerpo();
    const solicitud = this.id
      ? this.apiService.update('clinica/pacientes', this.id, cuerpo)
      : this.apiService.store('clinica/pacientes', cuerpo);

    solicitud.pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        const id = respuesta?.data?.id ?? this.id;
        const pendientes = this.responsables.filter((item) => item.local && item.vigente !== false);
        if (!this.id && id && pendientes.length) {
          this.vincularPendientes(id, pendientes);
          return;
        }
        this.guardando = false;
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

  abrirResponsable(template: TemplateRef<any>, vinculo?: any): void {
    this.editandoResponsable = vinculo?.id ?? null;
    this.clientesEncontrados = [];
    this.responsable = vinculo
      ? { ...this.responsableVacio(), rol: vinculo.rol, es_principal: vinculo.es_principal, nombre: vinculo.nombre }
      : this.responsableVacio();
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  cerrarModal(): void {
    this.modalRef?.hide();
    this.alertService.modal = false;
  }

  buscarClientes(): void {
    const q = (this.responsable.busqueda || '').trim();
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
    const cuerpo = this.cuerpoResponsable();
    if (!cuerpo) {
      return;
    }
    if (!this.id) {
      this.guardarResponsableLocal(cuerpo);
      return;
    }
    const solicitud = this.editandoResponsable
      ? this.apiService.update('clinica/pacientes/' + this.id + '/responsables', this.editandoResponsable, cuerpo)
      : this.apiService.store('clinica/pacientes/' + this.id + '/responsables', cuerpo);
    solicitud.pipe(this.untilDestroyed()).subscribe({
      next: (respuesta) => {
        this.responsables = respuesta?.data?.responsables ?? this.responsables;
        this.cerrarModal();
        this.alertService.success('Listo', 'Responsable guardado. El paciente no se convirtió en cliente.');
      },
      error: (error) => this.alertService.error(error),
    });
  }

  desactivarResponsable(vinculo: any): void {
    if (vinculo.local || !this.id) {
      this.responsables = this.responsables.filter((item) => item.id !== vinculo.id);
      return;
    }
    this.apiService.patch('clinica/pacientes', this.id + '/responsables/' + vinculo.id, {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (respuesta) => {
          this.responsables = respuesta?.data?.responsables ?? this.responsables;
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

  private cuerpoResponsable(): any | null {
    const cuerpo: any = {
      rol: this.responsable.rol,
      es_principal: this.responsable.rol === 'principal' || this.responsable.es_principal,
    };
    if (this.editandoResponsable) {
      return cuerpo;
    }
    if (this.responsable.modo === 'paciente') {
      if (this.paciente.tipo !== 'HUMANO') {
        return null;
      }
      cuerpo.es_el_paciente = true;
      return cuerpo;
    }
    if (this.responsable.modo === 'cliente') {
      if (!this.responsable.id_cliente) {
        return null;
      }
      cuerpo.id_cliente = this.responsable.id_cliente;
      return cuerpo;
    }
    if (!String(this.responsable.nombre || '').trim()) {
      return null;
    }
    cuerpo.nombre = this.responsable.nombre;
    cuerpo.documento = this.responsable.documento;
    cuerpo.telefono = this.responsable.telefono;
    cuerpo.correo = this.responsable.correo;
    return cuerpo;
  }

  private guardarResponsableLocal(cuerpo: any): void {
    const esPrincipal = !!cuerpo.es_principal;
    if (esPrincipal) {
      this.responsables = this.responsables.map((item) => {
        if (item.id === this.editandoResponsable || !item.es_principal) {
          return item;
        }
        const rol = item.rol === 'principal' ? 'secundario' : item.rol;
        return {
          ...item,
          es_principal: false,
          rol,
          cuerpo: item.cuerpo ? { ...item.cuerpo, es_principal: false, rol } : item.cuerpo,
        };
      });
    }
    if (this.editandoResponsable) {
      this.responsables = this.responsables.map((item) => item.id === this.editandoResponsable
        ? {
            ...item,
            rol: cuerpo.rol,
            es_principal: esPrincipal,
            cuerpo: { ...(item.cuerpo ?? {}), rol: cuerpo.rol, es_principal: esPrincipal },
          }
        : item);
    } else {
      this.responsables = [...this.responsables, {
        id: this.siguienteLocal--,
        local: true,
        vigente: true,
        nombre: this.nombrePendiente(cuerpo),
        rol: cuerpo.rol,
        es_principal: esPrincipal,
        es_el_paciente: !!cuerpo.es_el_paciente,
        cuerpo,
      }];
    }
    this.cerrarModal();
  }

  private nombrePendiente(cuerpo: any): string {
    if (cuerpo.es_el_paciente) {
      return this.paciente.tipo === 'HUMANO'
        ? `${this.paciente.nombres || ''} ${this.paciente.apellidos || ''}`.trim() || 'El paciente'
        : (this.paciente.nombre || 'El paciente');
    }
    if (cuerpo.id_cliente) {
      const cliente = this.clientesEncontrados.find((item) => String(item.id) === String(cuerpo.id_cliente));
      return cliente ? this.nombreCliente(cliente) : 'Cliente';
    }
    return cuerpo.nombre;
  }

  private vincularPendientes(id: number, lista: any[], indice = 0): void {
    if (indice >= lista.length) {
      this.guardando = false;
      this.alertService.success('Listo', 'Paciente guardado. No se creó un cliente.');
      this.router.navigate(['/clinica/pacientes', id]);
      return;
    }
    this.apiService.store('clinica/pacientes/' + id + '/responsables', lista[indice].cuerpo)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => this.vincularPendientes(id, lista, indice + 1),
        error: (error) => {
          this.guardando = false;
          this.alertService.error(error);
          this.router.navigate(['/clinica/pacientes', id, 'editar']);
        },
      });
  }

  private responsableVacio(): any {
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
        this.responsables = data.responsables ?? [];
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
