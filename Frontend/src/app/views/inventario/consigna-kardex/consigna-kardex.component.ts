import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FuncionalidadesService } from '@services/functionalities.service';

@Component({
  selector: 'app-consigna-kardex',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './consigna-kardex.component.html',
})
export class ConsignaKardexComponent implements OnInit {
  public movimientos: any[] = [];
  public loading = false;
  public downloading = false;
  public moduloActivo = false;
  public filtros: any = {
    fecha_ini: '',
    fecha_fin: '',
    buscador: '',
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private funcionalidadesService: FuncionalidadesService
  ) {}

  ngOnInit() {
    this.funcionalidadesService.verificarAcceso('modulo-consignaciones').subscribe({
      next: (acceso) => {
        this.moduloActivo = acceso;
        if (acceso) {
          this.loadAll();
        }
      },
      error: () => {
        this.moduloActivo = false;
      },
    });
  }

  public loadAll() {
    this.loading = true;
    this.apiService.getAll('productos/consigna-kardex', this.filtros).subscribe({
      next: (data) => {
        this.movimientos = Array.isArray(data) ? data : data?.data ?? [];
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  public exportar() {
    this.downloading = true;
    this.apiService.export('productos/consigna-kardex/exportar', this.filtros).subscribe({
      next: (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'consigna-kardex.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.downloading = false;
      },
    });
  }
}
