import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ComisionPeriodo, ComisionesService } from '@services/comisiones.service';

@Component({
  selector: 'app-periodos-liquidaciones-comisiones',
  standalone: false,
  templateUrl: './periodos-liquidaciones.component.html',
  styleUrls: ['./periodos-liquidaciones.component.css']
})
export class PeriodosLiquidacionesComponent implements OnInit {
  periodos: ComisionPeriodo[] = [];
  loading = false;
  procesandoPeriodoId: number | null = null;
  filtroEstado = '';

  constructor(
    private comisionesService: ComisionesService,
    private alertService: AlertService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.loadPeriodos();
  }

  loadPeriodos(): void {
    this.loading = true;
    this.comisionesService.getPeriodos(this.filtroEstado || undefined).subscribe({
      next: (response) => {
        this.periodos = response.data ?? [];
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  verDetalles(periodo: ComisionPeriodo): void {
    this.router.navigate(['/comisiones/periodos', periodo.id]);
  }

  cerrarPeriodo(periodo: ComisionPeriodo): void {
    if (!confirm(`¿Cerrar el período del ${this.formatFecha(periodo.fecha_inicio)} al ${this.formatFecha(periodo.fecha_fin)}?`)) {
      return;
    }

    this.procesandoPeriodoId = periodo.id;
    this.comisionesService.cerrarPeriodo(periodo.id).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', response.message || 'Período cerrado.');
        this.loadPeriodos();
        this.procesandoPeriodoId = null;
      },
      error: (error) => {
        this.alertService.error(error);
        this.procesandoPeriodoId = null;
      }
    });
  }

  estadoBadgeClass(estado: string): string {
    switch (estado) {
      case 'abierto':
        return 'bg-info';
      case 'cerrado':
        return 'bg-warning text-dark';
      case 'pagado':
        return 'bg-success';
      default:
        return 'bg-secondary';
    }
  }

  formatFecha(fecha: string): string {
    if (!fecha) {
      return '';
    }
    return fecha.substring(0, 10);
  }
}
