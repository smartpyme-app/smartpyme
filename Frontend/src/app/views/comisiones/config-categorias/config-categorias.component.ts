import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import {
  ComisionAlcance,
  ComisionCategoriaConfig,
  ComisionMomento,
  ComisionRegla,
  ComisionReglaPayload,
  ComisionSubcategoriaConfig,
  ComisionTipoCalculo,
  ComisionTramoVolumen,
  ComisionesService
} from '@services/comisiones.service';

type EditTarget =
  | { tipo: 'categoria'; item: ComisionCategoriaConfig }
  | { tipo: 'subcategoria'; item: ComisionSubcategoriaConfig; padreNombre: string };

@Component({
  selector: 'app-config-categorias-comisiones',
  standalone: false,
  templateUrl: './config-categorias.component.html',
  styleUrls: ['./config-categorias.component.css']
})
export class ConfigCategoriasComponent implements OnInit {
  reglas: ComisionRegla[] = [];
  vendedores: { id: number; name: string }[] = [];
  categorias: any = {};
  loading = false;
  loadingReglas = false;
  saving = false;
  expandedCategoriaId: number | null = null;
  filtros: any = { page: 1, paginate: 25 };
  reglaSeleccionada: ComisionRegla | null = null;
  mostrarFormulario = false;
  editandoId: number | null = null;

  form: {
    nombre: string;
    tipo_calculo: ComisionTipoCalculo;
    alcance: ComisionAlcance;
    momento_devengo: ComisionMomento;
    id_vendedores: number[];
    reemplaza_global: boolean;
    activo: boolean;
    salario_base: number | null;
    porcentaje: number | null;
    tramos: ComisionTramoVolumen[];
  } = this.formularioVacio();

  editTarget: EditTarget | null = null;
  editPorcentaje: number | null = null;
  modalRef?: BsModalRef;

  constructor(
    private comisionesService: ComisionesService,
    private alertService: AlertService,
    private modalManager: ModalManagerService,
    private apiService: ApiService
  ) {}

  ngOnInit(): void {
    this.loadVendedores();
    this.loadReglas();
  }

  loadVendedores(): void {
    this.apiService.getAll('usuarios/list').subscribe({
      next: (usuarios: any) => {
        this.vendedores = Array.isArray(usuarios) ? usuarios : (usuarios?.data ?? []);
      },
      error: () => {
        this.vendedores = [];
      }
    });
  }

  loadReglas(): void {
    this.loadingReglas = true;
    this.comisionesService.getReglas().subscribe({
      next: (response) => {
        this.reglas = response.data ?? [];
        this.loadingReglas = false;
        if (!this.reglaSeleccionada && this.reglas.length) {
          this.seleccionarRegla(this.reglaPorCategoriaDefault() ?? this.reglas[0]);
        } else if (this.reglaSeleccionada) {
          const actual = this.reglas.find((r) => r.id === this.reglaSeleccionada?.id);
          if (actual) {
            this.seleccionarRegla(actual);
          }
        }
      },
      error: (error) => {
        this.alertService.error(error);
        this.loadingReglas = false;
      }
    });
  }

  loadCategorias(): void {
    if (!this.muestraCategorias) {
      this.categorias = {};
      return;
    }

    this.loading = true;
    const filtros = { ...this.filtros };
    if (this.reglaSeleccionada?.id) {
      filtros['id_regla'] = this.reglaSeleccionada.id;
    }
    this.comisionesService.getCategorias(filtros).subscribe({
      next: (response) => {
        this.categorias = {
          data: response.data ?? [],
          current_page: response.meta?.current_page ?? 1,
          last_page: response.meta?.last_page ?? 1,
          per_page: response.meta?.per_page ?? 25,
          total: response.meta?.total ?? 0,
        };
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  seleccionarRegla(regla: ComisionRegla): void {
    this.reglaSeleccionada = regla;
    this.expandedCategoriaId = null;
    this.filtros.page = 1;
    this.loadCategorias();
  }

  nuevaRegla(): void {
    this.editandoId = null;
    this.form = this.formularioVacio();
    this.mostrarFormulario = true;
  }

  editarRegla(regla: ComisionRegla): void {
    this.seleccionarRegla(regla);
    this.editandoId = regla.id;
    this.form = {
      nombre: regla.nombre,
      tipo_calculo: regla.tipo_calculo,
      alcance: regla.alcance || 'global',
      momento_devengo: regla.momento_devengo || 'al_pagar',
      id_vendedores: regla.id_vendedores?.length ? [...regla.id_vendedores] : [],
      reemplaza_global: !!regla.reemplaza_global,
      activo: regla.activo,
      salario_base: regla.config?.salario_base ?? null,
      porcentaje: regla.config?.porcentaje ?? null,
      tramos: regla.config?.tramos?.length
        ? regla.config.tramos.map((t) => ({ umbral: t.umbral, porcentaje: t.porcentaje }))
        : [{ umbral: 0, porcentaje: 0 }]
    };
    this.mostrarFormulario = true;
  }

  cancelarFormulario(): void {
    this.mostrarFormulario = false;
    this.editandoId = null;
    this.form = this.formularioVacio();
  }

  agregarTramo(): void {
    this.form.tramos.push({ umbral: 0, porcentaje: 0 });
  }

  quitarTramo(index: number): void {
    if (this.form.tramos.length > 1) {
      this.form.tramos.splice(index, 1);
    }
  }

  toggleVendedor(id: number, checked: boolean): void {
    if (checked) {
      if (!this.form.id_vendedores.includes(id)) {
        this.form.id_vendedores = [...this.form.id_vendedores, id];
      }
      return;
    }
    this.form.id_vendedores = this.form.id_vendedores.filter((v) => v !== id);
  }

  vendedorSeleccionado(id: number): boolean {
    return this.form.id_vendedores.includes(id);
  }

  guardarRegla(): void {
    if (!this.form.nombre.trim()) {
      this.alertService.warning('Atención', 'Ingrese un nombre para la regla.');
      return;
    }

    const payload = this.buildPayload();
    if (!payload) {
      return;
    }

    this.saving = true;
    const request = this.editandoId
      ? this.comisionesService.actualizarRegla(this.editandoId, payload)
      : this.comisionesService.crearRegla(payload);

    request.subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Regla guardada.');
        this.saving = false;
        this.reglaSeleccionada = response.data ?? this.reglaSeleccionada;
        this.cancelarFormulario();
        this.loadReglas();
      },
      error: (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    });
  }

  desactivarRegla(regla: ComisionRegla): void {
    if (!confirm(`¿Desactivar la regla "${regla.nombre}"?`)) {
      return;
    }

    this.comisionesService.actualizarRegla(regla.id, { activo: false }).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Regla desactivada.');
        this.loadReglas();
      },
      error: (error) => {
        this.alertService.error(error);
      }
    });
  }

  setPagination(event: any): void {
    if (!event || typeof event.page === 'undefined') {
      return;
    }
    this.filtros.page = event.page;
    this.expandedCategoriaId = null;
    this.loadCategorias();
  }

  toggleExpand(idCategoria: number): void {
    this.expandedCategoriaId = this.expandedCategoriaId === idCategoria ? null : idCategoria;
  }

  formatoPorcentaje(valor: number | null | undefined): string {
    if (valor === null || valor === undefined) {
      return 'Hereda categoría';
    }
    return `${valor}%`;
  }

  abrirEditarCategoria(template: TemplateRef<any>, categoria: ComisionCategoriaConfig): void {
    this.editTarget = { tipo: 'categoria', item: categoria };
    this.editPorcentaje = categoria.porcentaje;
    this.modalRef = this.modalManager.openModal(template, { size: 'md', backdrop: 'static' });
  }

  abrirEditarSubcategoria(
    template: TemplateRef<any>,
    sub: ComisionSubcategoriaConfig,
    padreNombre: string
  ): void {
    this.editTarget = { tipo: 'subcategoria', item: sub, padreNombre };
    this.editPorcentaje = sub.porcentaje;
    this.modalRef = this.modalManager.openModal(template, { size: 'md', backdrop: 'static' });
  }

  cerrarModal(): void {
    this.modalManager.closeModal(this.modalRef);
    this.editTarget = null;
    this.editPorcentaje = null;
    this.saving = false;
  }

  get editNombre(): string {
    if (!this.editTarget) {
      return '';
    }
    return this.editTarget.item.nombre;
  }

  get editEsSubcategoria(): boolean {
    return this.editTarget?.tipo === 'subcategoria';
  }

  get editPadreNombre(): string {
    return this.editTarget?.tipo === 'subcategoria' ? this.editTarget.padreNombre : '';
  }

  get muestraCategorias(): boolean {
    return this.reglaSeleccionada?.tipo_calculo === 'por_categoria';
  }

  get muestraVendedores(): boolean {
    return this.form.alcance !== 'global';
  }

  tipoLabel(tipo: string): string {
    const labels: Record<string, string> = {
      por_categoria: 'Por categoría',
      por_volumen: 'Por volumen',
      por_margen: 'Por margen'
    };
    return labels[tipo] ?? tipo;
  }

  alcanceLabel(regla: ComisionRegla): string {
    const n = regla.id_vendedores?.length ?? 0;
    if (regla.alcance === 'individual') {
      return 'Individual';
    }
    if (regla.alcance === 'equipo') {
      return `Equipo (${n})`;
    }
    return 'Global';
  }

  guardarDesdeModal(): void {
    if (!this.editTarget) {
      return;
    }

    if (this.editPorcentaje === null || this.editPorcentaje === undefined || Number.isNaN(this.editPorcentaje)) {
      this.alertService.warning('Atención', 'Ingrese un porcentaje válido.');
      return;
    }

    if (this.editPorcentaje < 0 || this.editPorcentaje > 100) {
      this.alertService.warning('Atención', 'El porcentaje debe estar entre 0 y 100.');
      return;
    }

    this.saving = true;
    const idRegla = this.reglaSeleccionada?.id;

    if (this.editTarget.tipo === 'categoria') {
      const categoria = this.editTarget.item;
      this.comisionesService.actualizarCategoria(categoria.id_categoria, this.editPorcentaje, idRegla).subscribe({
        next: (response) => {
          categoria.porcentaje = this.editPorcentaje as number;
          this.alertService.success('Éxito', response.message || 'Porcentaje de categoría actualizado.');
          this.cerrarModal();
        },
        error: (error) => {
          this.alertService.error(error);
          this.saving = false;
        }
      });
      return;
    }

    const sub = this.editTarget.item;
    this.comisionesService.actualizarSubcategoria(sub.id_subcategoria, this.editPorcentaje, idRegla).subscribe({
      next: (response) => {
        sub.porcentaje = this.editPorcentaje;
        this.alertService.success('Éxito', response.message || 'Override de subcategoría guardado.');
        this.cerrarModal();
      },
      error: (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    });
  }

  private reglaPorCategoriaDefault(): ComisionRegla | undefined {
    return this.reglas.find((r) => r.tipo_calculo === 'por_categoria' && r.alcance === 'global' && r.activo)
      ?? this.reglas.find((r) => r.tipo_calculo === 'por_categoria');
  }

  private buildPayload(): ComisionReglaPayload | null {
    if (this.form.alcance === 'individual' && this.form.id_vendedores.length !== 1) {
      this.alertService.warning('Atención', 'Seleccione exactamente un vendedor.');
      return null;
    }

    if (this.form.alcance === 'equipo' && !this.form.id_vendedores.length) {
      this.alertService.warning('Atención', 'Seleccione al menos un vendedor.');
      return null;
    }

    const config: ComisionRegla['config'] = {};
    if (this.form.salario_base !== null && this.form.salario_base !== undefined) {
      config.salario_base = this.form.salario_base;
    }

    if (this.form.tipo_calculo === 'por_margen') {
      if (this.form.porcentaje === null) {
        this.alertService.warning('Atención', 'El porcentaje de margen es requerido.');
        return null;
      }
      config.porcentaje = this.form.porcentaje;
    }

    if (this.form.tipo_calculo === 'por_volumen') {
      const tramos = this.form.tramos.filter((t) => t.umbral !== null && t.porcentaje !== null);
      if (!tramos.length) {
        this.alertService.warning('Atención', 'Agregue al menos un tramo válido.');
        return null;
      }
      config.tramos = tramos;
    }

    return {
      nombre: this.form.nombre.trim(),
      tipo_calculo: this.form.tipo_calculo,
      alcance: this.form.alcance,
      id_vendedores: this.form.alcance === 'global' ? null : this.form.id_vendedores,
      momento_devengo: this.form.momento_devengo,
      reemplaza_global: this.form.reemplaza_global,
      activo: this.form.activo,
      salario_base: this.form.salario_base ?? 0,
      config
    };
  }

  private formularioVacio() {
    return {
      nombre: '',
      tipo_calculo: 'por_categoria' as const,
      alcance: 'global' as const,
      momento_devengo: 'al_pagar' as const,
      id_vendedores: [] as number[],
      reemplaza_global: false,
      activo: true,
      salario_base: null as number | null,
      porcentaje: null as number | null,
      tramos: [{ umbral: 0, porcentaje: 0 }] as ComisionTramoVolumen[]
    };
  }
}
