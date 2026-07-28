import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import {
  ComisionCategoriaConfig,
  ComisionSubcategoriaConfig,
  ComisionesService
} from '@services/comisiones.service';

@Component({
  selector: 'app-config-categorias-comisiones',
  templateUrl: './config-categorias.component.html',
  styleUrls: ['./config-categorias.component.css']
})
export class ConfigCategoriasComponent implements OnInit {
  categorias: ComisionCategoriaConfig[] = [];
  loading = false;
  savingCategoriaId: number | null = null;
  savingSubcategoriaId: number | null = null;
  expandedCategoriaId: number | null = null;

  constructor(
    private comisionesService: ComisionesService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    this.loadCategorias();
  }

  loadCategorias(): void {
    this.loading = true;
    this.comisionesService.getCategorias().subscribe({
      next: (response) => {
        this.categorias = response.data ?? [];
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  toggleExpand(idCategoria: number): void {
    this.expandedCategoriaId = this.expandedCategoriaId === idCategoria ? null : idCategoria;
  }

  guardarCategoria(categoria: ComisionCategoriaConfig): void {
    this.savingCategoriaId = categoria.id_categoria;
    this.comisionesService.actualizarCategoria(categoria.id_categoria, categoria.porcentaje).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Porcentaje de categoría actualizado.');
        this.savingCategoriaId = null;
      },
      error: (error) => {
        this.alertService.error(error);
        this.savingCategoriaId = null;
      }
    });
  }

  guardarSubcategoria(sub: ComisionSubcategoriaConfig): void {
    if (sub.porcentaje === null || sub.porcentaje === undefined) {
      this.alertService.warning('Atención', 'Ingrese un porcentaje para la subcategoría.');
      return;
    }

    this.savingSubcategoriaId = sub.id_subcategoria;
    this.comisionesService.actualizarSubcategoria(sub.id_subcategoria, sub.porcentaje).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Override de subcategoría guardado.');
        this.savingSubcategoriaId = null;
      },
      error: (error) => {
        this.alertService.error(error);
        this.savingSubcategoriaId = null;
      }
    });
  }
}
