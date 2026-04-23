import { Component, OnInit, TemplateRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TagInputModule } from 'ngx-chips';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { FuncionalidadesService } from '@services/functionalities.service';
import { FilterPipe } from '@pipes/filter.pipe';
import { DuplicateCheckService } from '@services/duplicate-check.service';
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';
import Swal from 'sweetalert2';
import { LazyImageDirective } from '../../../../../directives/lazy-image.directive';
import {
    ContribuyenteActividadOption,
    extractNombreContribuyenteDesdeAe,
    mapContribuyenteAeResponseToActividades,
} from '@services/facturacion-electronica/contribuyente-hacienda.mapper';
import { finalize } from 'rxjs/operators';

@Component({
    selector: 'app-cliente-informacion',
    templateUrl: './cliente-informacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TagInputModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ClienteInformacionComponent extends BaseModalComponent implements OnInit {
  public cliente: any = {
    contactos: [], // Inicializar el array de contactos
  };
  public override loading = false;
  public override saving = false;
  public paises: any = [];
  public departamentos: any = [];
  public distritos: any = [];
  public municipios: any = [];
  public actividad_economicas: any = [];
  public contacto: any = {};
  public vendedores: any = [];
  //loading
  public loading_contacto = false;
  public esNuevo = false;
  public tipoAnterior = '';
  public catalogo:any = [];
  public contabilidadHabilitada: boolean = false;
  public diasCreditoOpciones = [3, 8, 10, 15, 30, 45, 60];

  private cdr = inject(ChangeDetectorRef);

  /** Actividades económicas CR (Hacienda /fe/ae), mismo patrón que Mi cuenta. */
  actividadesContribuyenteCr: ContribuyenteActividadOption[] = [];
  actividadContribuyenteSeleccionada: ContribuyenteActividadOption | null = null;
  contribuyenteCargandoCr = false;
  private identificacionCrTimer: ReturnType<typeof setTimeout> | null = null;

  readonly compareActividadContribuyenteCr = (
      a: ContribuyenteActividadOption,
      b: ContribuyenteActividadOption,
  ): boolean => {
      if (!a || !b) {
          return false;
      }

      return this.normalizarCodigoActividadCr(a.codigo) === this.normalizarCodigoActividadCr(b.codigo);
  };

  puedeEditarCreditoCliente(): boolean {
    const tipo = this.apiService.auth_user()?.tipo || '';
    return ['Administrador', 'Supervisor', 'Supervisor Limitado'].includes(tipo);
  }

  onHabilitaCreditoChange() {
    if (this.cliente.habilita_credito && !this.cliente.dias_credito) {
      const clasificacion = this.cliente.clasificacion?.toUpperCase();
      if (clasificacion === 'A' || clasificacion === 'B') {
        this.cliente.dias_credito = 30;
      } else if (clasificacion === 'C') {
        this.cliente.dias_credito = 15;
      } else {
        this.cliente.dias_credito = 30;
      }
    }
    if (!this.cliente.habilita_credito) {
      this.cliente.dias_credito = null;
      this.cliente.limite_credito = null;
    }
  }

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private route: ActivatedRoute,
    private router: Router,
    private funcionalidadesService: FuncionalidadesService,
    private duplicateCheckService: DuplicateCheckService,
    private feCrUbic: FeCrUbicacionService,
  ) {
    super(modalManager, alertService);
  }

    esCostaRicaFe(): boolean {
        return this.feCrUbic.esCostaRicaFe();
    }

    /** Debounce: al completar NIT (empresa) o cédula (persona) consulta Hacienda. */
    onIdentificacionClienteCrDebounced(): void {
        if (!this.esCostaRicaFe()) {
            return;
        }
        if (this.identificacionCrTimer !== null) {
            clearTimeout(this.identificacionCrTimer);
        }
        this.identificacionCrTimer = setTimeout(() => {
            this.identificacionCrTimer = null;
            this.cargarContribuyenteDesdeHacienda({ silenciarAlertaSinActividades: true });
        }, 600);
    }

    onActividadContribuyenteCrChange(item: ContribuyenteActividadOption | null): void {
        if (item) {
            this.cliente.cod_giro = item.codigo;
            this.cliente.giro = item.descripcion;
        } else {
            this.cliente.cod_giro = null;
            this.cliente.giro = '';
        }
        this.cdr.markForCheck();
    }

    private normalizarCodigoActividadCr(codigo: string): string {
        return String(codigo ?? '').replace(/\D/g, '');
    }

    private aplicarPaisPorDefectoDesdeEmpresa(): void {
        if (!this.esCostaRicaFe() || !this.esNuevo || !this.paises?.length) {
            return;
        }
        const emp = this.apiService.auth_user()?.empresa;
        if (!emp) {
            return;
        }
        let hit: { cod: string | number; nombre: string } | undefined = undefined;
        const codEmp = emp.cod_pais != null ? String(emp.cod_pais).trim() : '';
        if (codEmp !== '') {
            hit = this.paises.find((p: any) => String(p.cod) === codEmp);
        }
        if (!hit && emp.pais) {
            hit = this.paises.find((p: any) => p.nombre === emp.pais);
        }
        if (hit) {
            this.cliente.cod_pais = hit.cod;
            this.cliente.pais = hit.nombre;
            this.cdr.markForCheck();
        }
    }

    private cargarContribuyenteDesdeHacienda(opciones?: {
        silenciarAlertaSinActividades?: boolean;
    }): void {
        if (!this.esCostaRicaFe()) {
            return;
        }
        let raw = '';
        if (this.cliente.tipo === 'Empresa') {
            raw = String(this.cliente?.nit ?? '');
        } else if (this.cliente.tipo === 'Persona') {
            raw = String(this.cliente?.dui ?? '');
        } else {
            return;
        }
        const id = raw.replace(/\D/g, '');
        if (id.length < 9 || id.length > 12) {
            return;
        }

        this.contribuyenteCargandoCr = true;
        this.cdr.markForCheck();
        this.apiService
            .getAll('fe-cr/contribuyente', { identificacion: id })
            .pipe(
                this.untilDestroyed(),
                finalize(() => {
                    this.contribuyenteCargandoCr = false;
                    this.cdr.markForCheck();
                }),
            )
            .subscribe({
                next: (body) => {
                    const nombre = extractNombreContribuyenteDesdeAe(body);
                    if (nombre) {
                        if (this.cliente.tipo === 'Empresa' && !String(this.cliente.nombre_empresa ?? '').trim()) {
                            this.cliente.nombre_empresa = nombre;
                        } else if (this.cliente.tipo === 'Persona' && !String(this.cliente.nombre ?? '').trim()) {
                            this.cliente.nombre = nombre;
                        }
                    }
                    const list = mapContribuyenteAeResponseToActividades(body);
                    const sel = this.actividadContribuyenteSeleccionada;
                    let merged = list;
                    if (sel?.codigo) {
                        if (list.length > 0 && !list.some((a) => this.compareActividadContribuyenteCr(a, sel))) {
                            merged = [sel, ...list];
                        }
                        if (list.length === 0) {
                            merged = [sel];
                        }
                    }
                    this.actividadesContribuyenteCr = merged;
                    if (
                        list.length === 0 &&
                        !opciones?.silenciarAlertaSinActividades
                    ) {
                        this.alertService.warning(
                            'Hacienda',
                            'No se encontraron actividades económicas para esta identificación. Verifique el número o intente más tarde.',
                        );
                    }
                    this.reconciliarSeleccionActividadContribuyenteCr();
                    this.cdr.markForCheck();
                },
                error: (e) => this.alertService.error(e),
            });
    }

    private reconciliarSeleccionActividadContribuyenteCr(): void {
        const sel = this.actividadContribuyenteSeleccionada;
        if (!sel?.codigo) {
            return;
        }
        const hit = this.actividadesContribuyenteCr.find((a) =>
            this.compareActividadContribuyenteCr(a, sel),
        );
        if (hit) {
            this.actividadContribuyenteSeleccionada = hit;
            this.onActividadContribuyenteCrChange(hit);
        }
        this.cdr.markForCheck();
    }

    private syncActividadContribuyenteCrDesdeCliente(): void {
        if (!this.esCostaRicaFe()) {
            return;
        }
        const rawCod = String(this.cliente?.cod_giro ?? '').trim();
        const desc = String(this.cliente?.giro ?? '').trim();
        const soloDigitos = rawCod.replace(/\D/g, '');
        if (soloDigitos.length === 13) {
            this.actividadContribuyenteSeleccionada = null;
            this.actividadesContribuyenteCr = [];
            this.cdr.markForCheck();

            return;
        }
        if (rawCod === '' && desc === '') {
            this.actividadContribuyenteSeleccionada = null;
            this.cdr.markForCheck();

            return;
        }
        const synthetic: ContribuyenteActividadOption = {
            codigo: rawCod,
            descripcion: desc,
            label: desc !== '' ? `${rawCod} — ${desc}` : rawCod,
        };
        this.actividadContribuyenteSeleccionada = synthetic;
        if (!this.actividadesContribuyenteCr.some((a) => this.compareActividadContribuyenteCr(a, synthetic))) {
            this.actividadesContribuyenteCr = [synthetic, ...this.actividadesContribuyenteCr];
        }
        this.reconciliarSeleccionActividadContribuyenteCr();
        this.cdr.markForCheck();
    }

    municipiosFiltradosCr(): any[] {
        return this.feCrUbic.municipiosPorProvincia(this.municipios, this.cliente?.cod_departamento);
    }

    distritosFiltradosCr(): any[] {
        return this.feCrUbic.distritosPorCanton(
            this.distritos,
            this.cliente?.cod_departamento,
            this.cliente?.cod_municipio,
        );
    }

    ngOnInit() {
        this.loadAll();
        this.paises = JSON.parse(localStorage.getItem('paises') || '[]');
        this.departamentos = JSON.parse(localStorage.getItem('departamentos') || '[]');
        this.distritos = JSON.parse(localStorage.getItem('distritos') || '[]');
        this.municipios = JSON.parse(localStorage.getItem('municipios') || '[]');
        this.actividad_economicas = JSON.parse(localStorage.getItem('actividad_economicas') || '[]');

        this.feCrUbic.cargarCatalogosYLs().subscribe((r) => {
            if (r) {
                this.departamentos = r.dep;
                this.municipios = r.mun;
                this.distritos = r.dis;
                this.cdr.markForCheck();
            }
        });

        this.destroyRef.onDestroy(() => {
            if (this.identificacionCrTimer !== null) {
                clearTimeout(this.identificacionCrTimer);
                this.identificacionCrTimer = null;
            }
        });

        // Verificar si tiene contabilidad habilitada
        this.verificarAccesoContabilidad();

        // Cargar vendedores
      this.apiService.getAll('usuarios/list').subscribe(
        (usuarios) => {
          this.vendedores = usuarios;
        },
        (error) => {
          this.alertService.error(error);
        }
      );
    }

    verificarAccesoContabilidad() {
        this.funcionalidadesService.verificarAcceso('contabilidad')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (acceso) => {
                    this.contabilidadHabilitada = acceso;
                    // Solo cargar catálogo si tiene contabilidad habilitada
                    if (acceso) {
                        this.apiService.getAll('catalogo/list')
                            .pipe(this.untilDestroyed())
                            .subscribe(catalogo => {
                                this.catalogo = catalogo;
                                this.cdr.markForCheck();
                            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
                    }
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    console.error('Error al verificar acceso a contabilidad:', error);
                    this.contabilidadHabilitada = false;
                    this.cdr.markForCheck();
                }
            });
    }

  public loadAll() {
    this.route.params.pipe(this.untilDestroyed()).subscribe((params: any) => {
      if (params.id) {
        this.esNuevo = false;
        this.loading = true;
        this.apiService.read('cliente/', params.id).pipe(this.untilDestroyed()).subscribe(
          (cliente) => {
            this.cliente = cliente;
            this.tipoAnterior = cliente.tipo;
            this.loading = false;
            if (!this.cliente.contactos) {
              this.cliente.contactos = [];
            }
            if (this.esCostaRicaFe()) {
              this.syncActividadContribuyenteCrDesdeCliente();
              queueMicrotask(() =>
                this.cargarContribuyenteDesdeHacienda({ silenciarAlertaSinActividades: true }),
              );
            }
            this.asegurarEtiquetasClienteArray();
            this.cdr.markForCheck();
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
            this.cdr.markForCheck();
          }
        );
      } else {
        this.esNuevo = true;
        this.cliente = {};
        this.cliente.tipo = 'Persona';
        this.cliente.contactos = [];
        this.cliente.tipo_contribuyente = '';
        this.cliente.habilita_credito = false;
        this.cliente.dias_credito = null;
        this.cliente.limite_credito = null;
        this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
        this.cliente.id_usuario = this.apiService.auth_user().id;
        if (this.esCostaRicaFe()) {
          this.aplicarPaisPorDefectoDesdeEmpresa();
        }
        this.cliente.etiquetas = [];
      }
    });
  }

  /** ngx-chips requiere un array; la API puede devolver null o JSON string. */
  private asegurarEtiquetasClienteArray(): void {
    const e = this.cliente?.etiquetas;
    if (e == null || e === '') {
      this.cliente.etiquetas = [];
      return;
    }
    if (Array.isArray(e)) {
      return;
    }
    if (typeof e === 'string') {
      try {
        const parsed = JSON.parse(e);
        this.cliente.etiquetas = Array.isArray(parsed) ? parsed : [];
      } catch {
        this.cliente.etiquetas = [];
      }
    }
  }

  public setTipo(tipo: any) {
    this.cliente.tipo = tipo;
  }

  setPais() {
    this.cliente.pais = this.paises.find(
      (item: any) => item.cod == this.cliente.cod_pais
    ).nombre;
  }

  setGiro() {
    this.cliente.giro = this.actividad_economicas.find(
      (item: any) => item.cod == this.cliente.cod_giro
    ).nombre;
    console.log(this.cliente.giro);
  }

  setDistrito() {
    let distrito = this.distritos.find(
      (item: any) =>
        item.cod == this.cliente.cod_distrito &&
        item.cod_departamento == this.cliente.cod_departamento
    );
    console.log(distrito);
    if (distrito) {
      this.cliente.cod_municipio = distrito.cod_municipio;
      const mun = this.municipios.find(
        (m: any) =>
          m.cod == distrito.cod_municipio && m.cod_departamento == distrito.cod_departamento,
      );
      if (mun) {
        this.cliente.municipio = mun.nombre;
      }
      this.cliente.distrito = distrito.nombre;
      this.cliente.cod_distrito = distrito.cod;
    }
    this.cdr.markForCheck();
  }

  setMunicipio() {
    let municipio = this.municipios.find(
      (item: any) =>
        item.cod == this.cliente.cod_municipio &&
        item.cod_departamento == this.cliente.cod_departamento
    );
    if (municipio) {
      this.cliente.municipio = municipio.nombre;
      this.cliente.cod_municipio = municipio.cod;

      this.cliente.distrito = '';
      this.cliente.cod_distrito = '';
    }
    this.cdr.markForCheck();
  }

  // Métodos getter para filtrar distritos y municipios
  get distritosFiltrados(): any[] {
    if (!this.distritos || !this.cliente.cod_departamento) {
      return [];
    }
    return this.distritos.filter((distrito: any) =>
      distrito.cod_departamento == this.cliente.cod_departamento
    );
  }

  get municipiosFiltrados(): any[] {
    if (!this.municipios || !this.cliente.cod_departamento) {
      return [];
    }
    return this.municipios.filter((municipio: any) =>
      municipio.cod_departamento == this.cliente.cod_departamento
    );
  }

  setDepartamento() {
    let departamento = this.departamentos.find(
      (item: any) => item.cod == this.cliente.cod_departamento
    );
    if (departamento) {
      this.cliente.departamento = departamento.nombre;
      this.cliente.cod_departamento = departamento.cod;
    }
    this.cliente.municipio = '';
    this.cliente.cod_municipio = '';
    this.cliente.distrito = '';
    this.cliente.cod_distrito = '';
    this.cdr.markForCheck();
  }

  public async onSubmit(): Promise<void> {
    this.saving = true;
    try {
      const routeUrl = this.esNuevo ? 'cliente' : 'cliente/update';
      const clienteGuardado = await this.apiService.store(routeUrl, this.cliente)
          .pipe(this.untilDestroyed())
          .toPromise();

      const titulo = this.esNuevo ? 'Cliente creado' : 'Cliente actualizado';
      const mensaje = this.esNuevo
        ? 'El cliente fue creado exitosamente.'
        : 'El cliente fue actualizado exitosamente.';

      this.alertService.success(titulo, mensaje);

      this.cliente = clienteGuardado;
      if (this.esNuevo) {
        this.router.navigate(['/cliente/editar', clienteGuardado.id]);
      }
    } catch (error: any) {
      this.alertService.error(error);
    } finally {
      this.saving = false;
    }
  }

  public verificarSiExiste() {
    if (this.cliente.nombre && this.cliente.apellido) {
      this.apiService
        .getAll('clientes', {
          nombre: this.cliente.nombre,
          apellido: this.cliente.apellido,
          estado: 1,
        })
        .pipe(this.untilDestroyed())
        .subscribe(
          (clientes) => {
            if (clientes.data[0]) {
              this.alertService.warning(
                '🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
                'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' +
                  this.apiService.appUrl +
                  '/cliente/editar/' +
                  clientes.data[0].id +
                  '">Ver cliente</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
              );
            }
            this.loading = false;
            this.cdr.markForCheck();
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
            this.cdr.markForCheck();
          }
        );
    }
  }

  // openModal(template: TemplateRef<any>, contacto: any) {
  //   this.contacto = contacto;
  //   this.modalRef = this.modalService.show(template, {
  //     class: 'modal-lg',
  //     backdrop: 'static',
  //   });
  // }

  public override openModal(template: TemplateRef<any>, contacto?: any) {

    if (!contacto || contacto === null) {

      this.contacto = {};
    } else {

      this.contacto = { ...contacto };
    }

    super.openModal(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  agregarContacto(template: TemplateRef<any>) {
    this.contacto = {};
    this.openModal(template, null);
  }

  submit(event: Event) {
    event.preventDefault();

    if (!this.cliente.contactos) {
      this.cliente.contactos = [];
    }

    if (!this.contacto.nombre && !this.contacto.apellido) {
      Swal.fire(
        '🚨 Alerta',
        'Debes ingresar al menos un nombre o apellido.',
        'warning'
      );
      return;
    }

    if (!this.contacto.telefono && !this.contacto.correo) {
      Swal.fire(
        '🚨 Alerta',
        'Debes ingresar al menos un teléfono o correo.',
        'warning'
      );
      return;
    }
    const nuevoContacto = {
      id: this.contacto.id || Date.now(),
      nombre: this.contacto.nombre,
      apellido: this.contacto.apellido,
      correo: this.contacto.correo,
      telefono: this.contacto.telefono,
      cargo: this.contacto.cargo,
      fecha_nacimiento: this.contacto.fecha_nacimiento,
      red_social: this.contacto.red_social,
      nota: this.contacto.nota,
      sexo: this.contacto.sexo,
      id_cliente: this.cliente.id,
    };

    if (this.cliente.id) {
      this.loading_contacto = true;

      this.apiService.store('cliente/contacto', nuevoContacto).pipe(this.untilDestroyed()).subscribe({
        next: (contactoGuardado) => {
          const index = this.cliente.contactos.findIndex(
            (c: any) => c.id === contactoGuardado.id
          );

          if (index !== -1) {
            this.cliente.contactos[index] = contactoGuardado;
          } else {
            this.cliente.contactos.push(contactoGuardado);
          }

          this.alertService.success(
            'Contacto guardado',
            'El contacto fue guardado exitosamente.'
          );

          this.contacto = {};
          this.loading_contacto = false;
          if (this.modalRef) {
            this.closeModal();
          }
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error('Error al guardar el contacto: ' + error);
          this.cdr.markForCheck();
          this.loading_contacto = false;
        },
      });
    } else {
      const index = this.cliente.contactos.findIndex(
        (c: any) => c.id === nuevoContacto.id
      );

      if (index !== -1) {
        this.cliente.contactos[index] = { ...nuevoContacto };
      } else {
        this.cliente.contactos.push(nuevoContacto);
      }

      this.contacto = {};
      if (this.modalRef) {
        this.closeModal();
      }

      this.alertService.success(
        'Contacto agregado',
        'El contacto fue agregado a la lista. Se guardará cuando guarde el cliente.'
      );
    }
  }

  eliminarContacto(contacto: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        if (contacto.id && this.cliente.id) {
          this.loading = true;

          this.apiService.delete('cliente/contacto/', contacto.id).pipe(this.untilDestroyed()).subscribe({
            next: () => {
              const index = this.cliente.contactos.findIndex(
                (c: any) => c.id === contacto.id
              );
              if (index !== -1) {
                this.cliente.contactos.splice(index, 1);
              }

              this.alertService.success(
                'Contacto eliminado',
                'El contacto fue eliminado exitosamente.'
              );
              this.loading = false;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(
                'Error al eliminar el contacto: ' + error
              );
              this.loading = false;
              this.cdr.markForCheck();
            },
          });
        } else {
          const index = this.cliente.contactos.findIndex(
            (c: any) => c.id === contacto.id
          );
          if (index !== -1) {
            this.cliente.contactos.splice(index, 1);
            this.alertService.success(
              'Contacto eliminado',
              'El contacto fue eliminado de la lista.'
            );
          }
        }
      }
    });
  }

  onTipoChange() {
      if (this.esNuevo) {
          // Creando: limpiar todo
          this.limpiarTodosSinTipo();
      } else {
          // Editando: mapeo inteligente
          const tipoAnterior = this.tipoAnterior;
          const nuevoTipo = this.cliente.tipo;
          this.mapearCamposEntreTipos(tipoAnterior, nuevoTipo);
      }

      this.tipoAnterior = this.cliente.tipo;
  }

  limpiarTodosSinTipo() {
    // Campos comunes
    this.cliente.codigo_cliente = '';
    this.cliente.nombre = '';
    this.cliente.apellido = '';
    this.cliente.correo = '';
    this.cliente.telefono = '';
    this.cliente.direccion = '';
    this.cliente.pais = '';
    this.cliente.departamento = '';
    this.cliente.municipio = '';
    this.cliente.distrito = '';

    // Campos de persona
    this.cliente.dui = '';
    this.cliente.fecha_cumpleanos = '';
    this.cliente.red_social = '';
    this.cliente.etiquetas = [];
    this.cliente.nota = '';

    // Campos de empresa
    this.cliente.nombre_empresa = '';
    this.cliente.nit = '';
    this.cliente.ncr = '';
    this.cliente.tipo_contribuyente = '';
    this.cliente.giro = '';
    this.cliente.empresa_telefono = '';
    this.cliente.empresa_direccion = '';

    // Campos de extranjero
    this.cliente.tipo_documento = '';
    this.cliente.tipo_persona = '';

    // Códigos de ubicación
    this.cliente.cod_pais = '';
    this.cliente.cod_departamento = '';
    this.cliente.cod_municipio = '';
    this.cliente.cod_distrito = '';
    this.cliente.cod_giro = '';
    this.actividadesContribuyenteCr = [];
    this.actividadContribuyenteSeleccionada = null;
    if (this.esNuevo && this.esCostaRicaFe()) {
      this.aplicarPaisPorDefectoDesdeEmpresa();
    }
  }

  mapearCamposEntreTipos(desde: string, hacia: string) {
    const datosComunes = {
        codigo_cliente: this.cliente.codigo_cliente,
        nombre: this.cliente.nombre,
        apellido: this.cliente.apellido,
        correo: this.cliente.correo,
        telefono: this.cliente.telefono,
        direccion: this.cliente.direccion,
        pais: this.cliente.pais,
        departamento: this.cliente.departamento,
        municipio: this.cliente.municipio,
        distrito: this.cliente.distrito
    };

    const mapeos: any = {
        'Persona->Empresa': {
            ...datosComunes,
            nombre_empresa: [this.cliente.nombre, this.cliente.apellido]
            .filter(Boolean)
            .join(' ') || this.cliente.nombre_empresa || '',
            empresa_telefono: this.cliente.telefono,
            empresa_direccion: this.cliente.direccion
        },
        'Empresa->Persona': {
            ...datosComunes,
            telefono: this.cliente.empresa_telefono || this.cliente.telefono,
            direccion: this.cliente.empresa_direccion || this.cliente.direccion
        },
        'Persona->Extranjero': {
            ...datosComunes,
            tipo_persona: 'Persona Natural',
            tipo_documento: '13', // DUI
            dui: this.cliente.dui
        },
        'Empresa->Extranjero': {
            ...datosComunes,
            nombre_empresa: this.cliente.nombre_empresa,
            tipo_persona: 'Persona Juridica',
            tipo_documento: '36', // NIT
            giro: this.cliente.giro,
            telefono: this.cliente.empresa_telefono || this.cliente.telefono
        },
        'Extranjero->Persona': {
            ...datosComunes,
            dui: this.cliente.dui
        },
        'Extranjero->Empresa': {
            ...datosComunes,
            nombre_empresa: this.cliente.nombre_empresa || this.cliente.nombre + ' ' + this.cliente.apellido,
            giro: this.cliente.giro,
            empresa_telefono: this.cliente.telefono
        }
    };

    const clave = `${desde}->${hacia}`;
    const mapeo = mapeos[clave];

    if (mapeo) {
        this.limpiarTodosSinTipo();
        Object.assign(this.cliente, mapeo);

        this.alertService.info(
            'Datos adaptados',
            'Los campos se han adaptado automáticamente al nuevo tipo de cliente.'
        );
    } else {
        this.limpiarTodosSinTipo();
        Object.assign(this.cliente, datosComunes);
    }
  }
}
