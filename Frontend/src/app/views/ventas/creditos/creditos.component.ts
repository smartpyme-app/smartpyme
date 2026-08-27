import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { ProgressbarModule } from 'ngx-bootstrap/progressbar';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { avanceCuotas, etiquetaEstadoContrato } from './creditos-cuotas';

@Component({
  selector: 'app-creditos',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule, PopoverModule, ProgressbarModule, TruncatePipe, PaginationComponent, CurrencyPipe],
  templateUrl: './creditos.component.html',
})
export class CreditosComponent implements OnInit {
  creditos: any = {};
  loading = false;
  filtros: any = {};
  etiquetaEstadoContrato = etiquetaEstadoContrato;
  avanceCuotas = avanceCuotas;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
  ) {}

  ngOnInit(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.filtros = {
      paginate: this.filtros?.paginate || 10,
      orden: this.filtros?.orden || 'id',
      direccion: this.filtros?.direccion || 'desc',
      page: 1,
      buscador: '',
    };
    this.filtrarCreditos(false);
  }

  filtrarCreditos(resetPage = true): void {
    if (resetPage) {
      this.filtros.page = 1;
    }
    this.loading = true;
    const page = this.filtros.page != null && this.filtros.page !== '' ? this.filtros.page : 1;
    const params: any = {
      paginate: this.filtros.paginate,
      orden: this.filtros.orden,
      direccion: this.filtros.direccion,
      page,
    };
    if (this.filtros.buscador) params.buscador = this.filtros.buscador;

    this.apiService.getAll('creditos-clientes', params).subscribe({
      next: (creditos) => {
        this.creditos = creditos;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  setOrden(columna: string): void {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.filtrarCreditos();
  }

  setPagination(event: any): void {
    this.filtros.page = event.page;
    this.filtrarCreditos(false);
  }

  etiquetaCliente(credito: any): string {
    return credito?.cliente?.nombre_completo || credito?.cliente?.nombre || '-';
  }
}
