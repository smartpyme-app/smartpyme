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
}
