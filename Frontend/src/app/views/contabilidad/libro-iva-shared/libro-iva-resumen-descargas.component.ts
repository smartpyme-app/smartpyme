import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { aplicarRangoMesLibroIva } from './libro-iva-filtros.util';
import { descargarBlob, manejarErrorDescargaLibroIva } from './libro-iva-descarga.util';

@Component({
  selector: 'app-libro-iva-resumen-descargas',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="dropdown">
      <button class="btn btn-default dropdown-toggle" type="button" data-bs-toggle="dropdown" [disabled]="downloading">
        Descargas
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li>
          <button type="button" class="dropdown-item" (click)="descargarExcel()">Resumen fiscal (Excel)</button>
        </li>
        <li>
          <button type="button" class="dropdown-item" (click)="descargarPdf()">Resumen fiscal (PDF)</button>
        </li>
      </ul>
    </div>
  `,
})
export class LibroIvaResumenDescargasComponent {
  @Input({ required: true }) filtros!: Record<string, unknown>;

  downloading = false;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService
  ) {}

  descargarExcel(): void {
    this.sincronizarFiltros();
    this.downloading = true;
    this.apiService.export('libro-iva/resumen-fiscal/descargar-excel', this.filtros).subscribe({
      next: (data: Blob) => {
        descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Resumen-fiscal.xlsx');
        this.downloading = false;
      },
      error: (error) => {
        manejarErrorDescargaLibroIva(error, this.alertService);
        this.downloading = false;
      },
    });
  }

  descargarPdf(): void {
    this.sincronizarFiltros();
    const token = this.apiService.auth_token();
    const query = new URLSearchParams(this.filtros as Record<string, string>).toString();
    const url = `${this.apiService.baseUrl}/api/libro-iva/resumen-fiscal?${query}&formato=pdf&token=${token}`;
    window.open(url, '_blank');
  }

  private sincronizarFiltros(): void {
    aplicarRangoMesLibroIva(this.filtros);
  }
}
