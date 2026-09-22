import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { ChartCardComponent } from '../../dashboard/components/chart-card/chart-card.component';
import { MetricCard } from '../../dashboard/models/chart-config.model';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-prestamos-list',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule, PaginationComponent, CurrencyPipe, ChartCardComponent],
  templateUrl: './prestamos-list.component.html',
})
export class PrestamosListComponent implements OnInit {
  prestamos: any = {};
  resumen: any = { deuda: 0, institucion: 0, persona: 0, proximos_7: 0, proximos_30: 0 };
  loading = false;
  filtros: any = {};
  puedeCrear = false;
  puedePagar = false;
  prestamosActivos: any[] = [];
  prestamoAbonoId: number | null = null;
  modalRef?: BsModalRef;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private modalService: BsModalService,
  ) {}

  ngOnInit(): void {
    this.puedeCrear = this.apiService.hasPermission('finanzas.prestamos.crear');
    this.puedePagar = this.apiService.hasPermission('finanzas.prestamos.pagar');
    this.loadAll();
  }

  loadAll(): void {
    this.filtros = {
      paginate: this.filtros?.paginate || 10,
      orden: this.filtros?.orden || 'id',
      direccion: this.filtros?.direccion || 'desc',
      page: 1,
      buscador: '',
      estado: '',
      tipo_acreedor: '',
    };
    this.filtrar(false);
  }

  filtrar(resetPage = true): void {
    if (resetPage) {
      this.filtros.page = 1;
    }
    this.loading = true;
    const params: any = {
      paginate: this.filtros.paginate,
      orden: this.filtros.orden,
      direccion: this.filtros.direccion,
      page: this.filtros.page || 1,
    };
    if (this.filtros.buscador) params.buscador = this.filtros.buscador;
    if (this.filtros.estado) params.estado = this.filtros.estado;
    if (this.filtros.tipo_acreedor) params.tipo_acreedor = this.filtros.tipo_acreedor;

    this.apiService.getAll('prestamos-empresa', params).subscribe({
      next: (res) => {
        this.prestamos = res;
        this.resumen = res?.resumen ?? this.resumen;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  abrirSelectorAbono(template: TemplateRef<any>): void {
    this.prestamoAbonoId = null;
    this.apiService.getAll('prestamos-empresa', { estado: 'activo', paginate: 100, orden: 'acreedor', direccion: 'asc' }).subscribe({
      next: (res) => {
        this.prestamosActivos = res?.data ?? [];
        if (!this.prestamosActivos.length) {
          this.alertService.error('No hay préstamos activos para abonar.');
          return;
        }
        this.modalRef = this.modalService.show(template, { class: 'modal-md' });
      },
      error: (err) => this.alertService.error(err),
    });
  }

  confirmarAbono(): void {
    if (!this.prestamoAbonoId) {
      this.alertService.error('Seleccione un préstamo.');
      return;
    }
    this.modalRef?.hide();
    this.irAbono(this.prestamoAbonoId);
  }

  irAbono(id: number): void {
    this.router.navigate(['/finanzas/prestamos', id], { queryParams: { abono: 1 } });
  }

  setPagination(ev: any): void {
    this.filtros.page = ev.page;
    this.filtrar(false);
  }

  setOrden(columna: string): void {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.filtrar();
  }

  claseOrden(columna: string): Record<string, boolean> {
    return {
      'sorted-asc': this.filtros.orden === columna && this.filtros.direccion === 'asc',
      'sorted-desc': this.filtros.orden === columna && this.filtros.direccion === 'desc',
    };
  }

  get kpis(): MetricCard[] {
    return [
      { title: 'Deuda total', value: this.resumen.deuda || 0, type: 'currency' },
      { title: 'Instituciones', value: this.resumen.institucion || 0, type: 'currency' },
      { title: 'Personas', value: this.resumen.persona || 0, type: 'currency' },
      { title: 'Próximos 7 días', value: this.resumen.proximos_7 || 0, type: 'currency' },
      { title: 'Próximos 30 días', value: this.resumen.proximos_30 || 0, type: 'currency' },
    ];
  }
}
