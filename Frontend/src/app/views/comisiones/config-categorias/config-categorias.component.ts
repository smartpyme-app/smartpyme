import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import {
  ComisionCategoriaConfig,
  ComisionSubcategoriaConfig,
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
  categorias: any = {};
  loading = false;
  saving = false;
  expandedCategoriaId: number | null = null;
  filtros: any = { page: 1, paginate: 25 };

  editTarget: EditTarget | null = null;
  editPorcentaje: number | null = null;
  modalRef?: BsModalRef;

  constructor(
    private comisionesService: ComisionesService,
    private alertService: AlertService,
    private modalManager: ModalManagerService
  ) {}

  ngOnInit(): void {
    this.loadCategorias();
  }

  loadCategorias(): void {
    this.loading = true;
    this.comisionesService.getCategorias(this.filtros).subscribe({
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

    if (this.editTarget.tipo === 'categoria') {
      const categoria = this.editTarget.item;
      this.comisionesService.actualizarCategoria(categoria.id_categoria, this.editPorcentaje).subscribe({
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
    this.comisionesService.actualizarSubcategoria(sub.id_subcategoria, this.editPorcentaje).subscribe({
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
}
