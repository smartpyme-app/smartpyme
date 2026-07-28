import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ComisionMovimiento, ComisionesService } from '@services/comisiones.service';

@Component({
  selector: 'app-reportes-comisiones',
  standalone: false,
  templateUrl: './reportes.component.html',
  styleUrls: ['./reportes.component.css']
})
export class ReportesComponent implements OnInit {
  desde = '';
  hasta = '';
  downloading = false;
  loadingMovimientos = false;
  movimientos: ComisionMovimiento[] = [];
  meta = { current_page: 1, last_page: 1, per_page: 25, total: 0 };

  constructor(
    private comisionesService: ComisionesService,
    private alertService: AlertService,
    private apiService: ApiService
  ) {}

  ngOnInit(): void {
    const hoy = new Date();
    const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    this.desde = this.toInputDate(inicioMes);
    this.hasta = this.toInputDate(hoy);
    this.loadMovimientos();
  }

  private toInputDate(date: Date): string {
    return date.toISOString().substring(0, 10);
  }

  descargarExcel(): void {
    if (!this.desde || !this.hasta) {
      this.alertService.warning('Atención', 'Seleccione el rango de fechas.');
      return;
    }

    this.downloading = true;
    this.comisionesService.exportExcel(this.desde, this.hasta).subscribe({
      next: (blob) => {
        this.apiService.downloadFile(blob, `comisiones-${this.desde}-${this.hasta}.xlsx`);
        this.downloading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.downloading = false;
      }
    });
  }

  loadMovimientos(page = 1): void {
    this.loadingMovimientos = true;
    const params: Record<string, unknown> = {
      paginate: 25,
      page
    };
    if (this.desde) {
      params['desde'] = this.desde;
    }
    if (this.hasta) {
      params['hasta'] = this.hasta;
    }

    this.comisionesService.getMovimientos(params).subscribe({
      next: (response) => {
        this.movimientos = response.data ?? [];
        this.meta = response.meta ?? this.meta;
        this.loadingMovimientos = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loadingMovimientos = false;
      }
    });
  }

  filtrarMovimientos(): void {
    this.loadMovimientos(1);
  }

  changePage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadMovimientos(page);
    }
  }
}
