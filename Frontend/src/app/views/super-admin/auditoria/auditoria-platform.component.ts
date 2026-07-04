import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '@services/api.service';
import { AuditoriaService } from '@services/auditoria.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { NgSelectModule } from '@ng-select/ng-select';

const MODULOS = [
  { value: '', label: 'Todos' },
  { value: 'ventas', label: 'Ventas' },
  { value: 'compras', label: 'Compras' },
  { value: 'inventario', label: 'Inventario' },
  { value: 'ajustes', label: 'Ajustes' },
];

@Component({
  selector: 'app-auditoria-platform',
  templateUrl: './auditoria-platform.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, PaginationComponent, NgSelectModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AuditoriaPlatformComponent implements OnInit {
  registros: any = { data: [] };
  empresas: any[] = [];
  modulos = MODULOS;
  loading = false;

  filtros: any = {
    id_empresa: null,
    module: '',
    fecha_inicio: '',
    fecha_fin: '',
    paginate: 25,
    page: 1,
  };

  constructor(
    private auditoriaService: AuditoriaService,
    private apiService: ApiService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.cargarEmpresas();
    this.cargar();
  }

  cargarEmpresas(): void {
    this.apiService.getAll('empresas/list').subscribe({
      next: (res) => {
        this.empresas = Array.isArray(res) ? res : res?.data ?? [];
        this.cdr.markForCheck();
      },
    });
  }

  cargar(page = 1): void {
    this.loading = true;
    this.filtros.page = page;
    const params = { ...this.filtros };
    if (!params.id_empresa) delete params.id_empresa;
    if (!params.module) delete params.module;
    if (!params.fecha_inicio) delete params.fecha_inicio;
    if (!params.fecha_fin) delete params.fecha_fin;

    this.auditoriaService.listPlatform(params).subscribe({
      next: (res) => {
        this.registros = res;
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.loading = false;
        this.cdr.markForCheck();
      },
    });
  }

  onFiltrar(): void {
    this.cargar(1);
  }

  setPage(page: number): void {
    this.cargar(page);
  }
}
