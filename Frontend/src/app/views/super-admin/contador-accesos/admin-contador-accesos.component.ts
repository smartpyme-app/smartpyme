import { Component, DestroyRef, inject, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgSelectModule } from '@ng-select/ng-select';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

interface PortalContador {
  id: number;
  name: string;
  email: string;
  enable?: boolean;
  empresas_activas_count?: number;
}

interface EmpresaLite {
  id: number;
  nombre: string;
}

interface ContadorAccesoRow {
  id: number;
  id_usuario_contador?: number;
  estado: string;
  contador?: PortalContador;
  empresa?: EmpresaLite;
}

interface ContadorConEmpresas {
  contador: PortalContador;
  accesosActivos: ContadorAccesoRow[];
}

interface UsuarioBusqueda {
  id: number;
  name: string;
  email: string;
  tipo?: string;
  empresa?: { nombre?: string };
}

@Component({
  selector: 'app-admin-contador-accesos',
  standalone: true,
  imports: [CommonModule, FormsModule, NgSelectModule],
  templateUrl: './admin-contador-accesos.component.html',
  styleUrls: ['./admin-contador-accesos.component.css'],
})
export class AdminContadorAccesosComponent implements OnInit {
  contadores: PortalContador[] = [];
  empresas: EmpresaLite[] = [];
  accesos: ContadorAccesoRow[] = [];

  contadorModal: PortalContador | null = null;
  idEmpresaModal: number | null = null;

  buscadorUsuario = '';
  usuariosBusqueda: UsuarioBusqueda[] = [];
  idUsuarioIncluir: number | null = null;

  loading = false;
  guardando = false;
  creando = false;
  buscandoUsuarios = false;
  incluyendo = false;
  nuevo = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  };

  modalRef?: BsModalRef;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private api: ApiService,
    private alert: AlertService,
    private modalService: BsModalService,
  ) {}

  ngOnInit(): void {
    this.cargarCatalogos();
    this.cargarAccesos();
  }

  get contadoresConEmpresas(): ContadorConEmpresas[] {
    const activos = this.accesos.filter((a) => a.estado === 'activo');
    const map = new Map<number, ContadorConEmpresas>();

    for (const acceso of activos) {
      const idContador = acceso.contador?.id ?? acceso.id_usuario_contador;
      if (!idContador) {
        continue;
      }
      let row = map.get(idContador);
      if (!row) {
        const contador =
          acceso.contador ??
          this.contadores.find((c) => c.id === idContador) ?? {
            id: idContador,
            name: '—',
            email: '',
          };
        row = { contador, accesosActivos: [] };
        map.set(idContador, row);
      }
      row.accesosActivos.push(acceso);
    }

    return [...map.values()].sort((a, b) =>
      a.contador.name.localeCompare(b.contador.name, 'es'),
    );
  }

  get accesosActivosModal(): ContadorAccesoRow[] {
    if (!this.contadorModal) {
      return [];
    }
    return this.accesos.filter(
      (a) =>
        a.estado === 'activo' &&
        (a.contador?.id ?? a.id_usuario_contador) === this.contadorModal!.id,
    );
  }

  get empresasDisponiblesModal(): EmpresaLite[] {
    const asignadas = new Set(this.accesosActivosModal.map((a) => a.empresa?.id));
    return this.empresas.filter((e) => !asignadas.has(e.id));
  }

  get idsContadoresPortal(): Set<number> {
    return new Set(this.contadores.map((c) => c.id));
  }

  /** Contadores del portal aún sin empresas activas (no aparecen en la tabla principal). */
  get contadoresSinEmpresas(): PortalContador[] {
    return this.contadores.filter((c) => !(c.empresas_activas_count ?? 0));
  }

  cargarContadores(): void {
    this.api
      .get('superadmin/contador-portal-usuarios')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (data) => {
          this.contadores = Array.isArray(data) ? data : [];
        },
        error: (err) => this.alert.error(err),
      });
  }

  cargarCatalogos(): void {
    this.cargarContadores();

    this.api
      .getAll('empresas/list')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (data) => {
          this.empresas = Array.isArray(data) ? data : [];
        },
        error: (err) => this.alert.error(err),
      });
  }

  cargarAccesos(): void {
    this.loading = true;
    this.api
      .getAll('superadmin/contador-accesos', { paginate: 500 })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (data) => {
          this.accesos = data?.data ?? [];
          this.loading = false;
        },
        error: (err) => {
          this.loading = false;
          this.alert.error(err);
        },
      });
  }

  abrirNuevoContador(template: TemplateRef<unknown>): void {
    this.nuevo = { name: '', email: '', password: '', password_confirmation: '' };
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  abrirIncluirContador(template: TemplateRef<unknown>): void {
    this.buscadorUsuario = '';
    this.usuariosBusqueda = [];
    this.idUsuarioIncluir = null;
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  abrirAgregarEmpresas(contador: PortalContador, template: TemplateRef<unknown>): void {
    this.contadorModal = contador;
    this.idEmpresaModal = null;
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  cerrarModal(): void {
    this.modalRef?.hide();
    this.contadorModal = null;
    this.idEmpresaModal = null;
    this.idUsuarioIncluir = null;
  }

  buscarUsuariosPlataforma(): void {
    const q = this.buscadorUsuario.trim();
    if (q.length < 2) {
      this.alert.warning('Búsqueda', 'Escribe al menos 2 caracteres (nombre o correo).');
      return;
    }

    this.buscandoUsuarios = true;
    this.api
      .getAll('admin-usuarios', {
        buscador: q,
        paginate: 25,
        orden: 'name',
        direccion: 'asc',
        estado: '',
        id_empresa: '',
        id_sucursal: '',
        tipo: '',
      })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (res) => {
          this.usuariosBusqueda = res?.data ?? [];
          this.idUsuarioIncluir = null;
          this.buscandoUsuarios = false;
          if (this.usuariosBusqueda.length === 0) {
            this.alert.info('Sin resultados', 'No se encontró ningún usuario con ese criterio.');
          }
        },
        error: (err) => {
          this.buscandoUsuarios = false;
          this.alert.error(err);
        },
      });
  }

  seleccionarUsuarioIncluir(u: UsuarioBusqueda): void {
    this.idUsuarioIncluir = u.id;
  }

  esContadorPortal(id: number): boolean {
    return this.idsContadoresPortal.has(id);
  }

  irAgregarEmpresasDesdeUsuario(u: UsuarioBusqueda, templateAgregar: TemplateRef<unknown>): void {
    this.modalRef?.hide();
    this.abrirAgregarEmpresas(
      { id: u.id, name: u.name, email: u.email },
      templateAgregar,
    );
  }

  incluirContadorExistente(templateAgregar: TemplateRef<unknown>): void {
    if (this.idUsuarioIncluir == null) {
      this.alert.warning('Selecciona un usuario', 'Elige quién será contador del portal.');
      return;
    }

    const seleccionado = this.usuariosBusqueda.find((u) => u.id === this.idUsuarioIncluir);
    if (seleccionado && this.esContadorPortal(seleccionado.id)) {
      this.irAgregarEmpresasDesdeUsuario(seleccionado, templateAgregar);
      return;
    }

    this.incluyendo = true;
    this.api
      .store('superadmin/contador-portal-usuario/incluir', { id_usuario: this.idUsuarioIncluir })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (usuario: PortalContador) => {
          this.incluyendo = false;
          this.alert.success('Listo', 'Usuario incluido como contador del portal.');
          this.modalRef?.hide();
          this.cargarContadores();
          if (usuario?.id) {
            this.abrirAgregarEmpresas(usuario, templateAgregar);
          }
        },
        error: (err) => {
          this.incluyendo = false;
          this.alert.error(err);
        },
      });
  }

  crearContador(templateAgregar?: TemplateRef<unknown>): void {
    if (this.nuevo.password !== this.nuevo.password_confirmation) {
      this.alert.warning('Contraseña', 'La confirmación no coincide.');
      return;
    }

    this.creando = true;
    this.api
      .store('superadmin/contador-portal-usuario', { ...this.nuevo })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (usuario: PortalContador) => {
          this.creando = false;
          this.alert.success('Contador creado', 'Ya puede ingresar al portal de contadores.');
          this.modalRef?.hide();
          this.cargarContadores();
          if (templateAgregar && usuario?.id) {
            this.abrirAgregarEmpresas(usuario, templateAgregar);
          }
        },
        error: (err) => {
          this.creando = false;
          this.alert.error(err);
        },
      });
  }

  asignarEmpresa(): void {
    const idContador = this.contadorModal?.id;
    if (!idContador || !this.idEmpresaModal) {
      this.alert.warning('Datos incompletos', 'Selecciona una empresa.');
      return;
    }

    this.guardando = true;
    this.api
      .store('superadmin/contador-acceso', {
        id_usuario_contador: idContador,
        id_empresa: this.idEmpresaModal,
      })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.guardando = false;
          this.idEmpresaModal = null;
          this.alert.success('Listo', 'Empresa asociada.');
          this.cargarAccesos();
          this.cargarContadores();
        },
        error: (err) => {
          this.guardando = false;
          this.alert.error(err);
        },
      });
  }

  revocar(acceso: ContadorAccesoRow): void {
    if (acceso.estado === 'revocado') {
      return;
    }
    this.api
      .delete('superadmin/contador-acceso/', acceso.id)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.alert.success('Listo', 'Empresa desasociada.');
          this.cargarAccesos();
          this.cargarContadores();
        },
        error: (err) => this.alert.error(err),
      });
  }
}
