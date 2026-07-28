import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ComisionesService } from '@services/comisiones.service';

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
  movimientos: any = {};
  filtros: any = { page: 1, paginate: 25 };

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

  loadMovimientos(): void {
    this.loadingMovimientos = true;
    const params: Record<string, unknown> = {
      ...this.filtros,
    };
    if (this.desde) {
      params['desde'] = this.desde;
    }
    if (this.hasta) {
      params['hasta'] = this.hasta;
    }

    this.comisionesService.getMovimientos(params).subscribe({
      next: (response) => {
        this.movimientos = {
          data: response.data ?? [],
          current_page: response.meta?.current_page ?? 1,
          last_page: response.meta?.last_page ?? 1,
          per_page: response.meta?.per_page ?? 25,
          total: response.meta?.total ?? 0,
        };
        this.loadingMovimientos = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loadingMovimientos = false;
      }
    });
  }

  filtrarMovimientos(): void {
    this.filtros.page = 1;
    this.loadMovimientos();
  }

  setPagination(event: any): void {
    if (!event || typeof event.page === 'undefined') {
      return;
    }
    this.filtros.page = event.page;
    this.loadMovimientos();
  }
}
