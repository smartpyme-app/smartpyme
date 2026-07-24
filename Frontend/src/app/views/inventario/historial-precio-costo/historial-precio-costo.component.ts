import { Component, Input, OnChanges, OnInit, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgChartsModule } from 'ng2-charts';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { getEmpresaCurrencySymbol } from '@helpers/currency-format.helper';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-historial-precio-costo',
  templateUrl: './historial-precio-costo.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, NgChartsModule, CurrencyPipe],
})
export class HistorialPrecioCostoComponent implements OnInit, OnChanges {

  @Input() producto: any = {};

  public usuarios: any[] = [];
  public filtros: any = {};
  public datos: any = {};
  public loading = false;

  public chartLabels: string[] = [];
  public chartDatasets: any[] = [];
  public chartOptions: any = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: true, position: 'top' },
      tooltip: { mode: 'index', intersect: false },
    },
    scales: {
      y: {
        beginAtZero: false,
        ticks: {
          callback: (value: number) => this.currencySymbol + Number(value).toFixed(2),
        },
      },
    },
    interaction: { mode: 'nearest', axis: 'x', intersect: false },
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService
  ) {}

  get currencySymbol(): string {
    return getEmpresaCurrencySymbol(this.apiService.auth_user()?.empresa);
  }

  ngOnInit(): void {
    const hoy = this.apiService.date();
    this.filtros.inicio = this.restarseMeses(hoy, 1);
    this.filtros.fin = hoy;
    this.filtros.id_usuario = '';

    this.apiService.getAll('usuarios/list').subscribe(
      usuarios => { this.usuarios = usuarios; },
      error => { this.alertService.error(error); }
    );

    if (this.producto?.id) {
      this.loadAll();
    }
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['producto'] && this.producto?.id) {
      this.loadAll();
    } else if (changes['producto'] && !this.producto?.id) {
      this.resetDatos();
    }
  }

  loadAll(): void {
    if (!this.producto?.id || !this.filtros.inicio || !this.filtros.fin) {
      return;
    }

    this.loading = true;
    const params: any = {
      inicio: this.filtros.inicio,
      fin: this.filtros.fin,
    };
    if (this.filtros.id_usuario) {
      params.id_usuario = this.filtros.id_usuario;
    }

    this.apiService.getAll('producto/historial-precio-costo/' + this.producto.id, params).subscribe(
      datos => {
        this.datos = datos;
        this.actualizarGrafica();
        this.loading = false;
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  actualizarGrafica(): void {
    this.chartLabels = this.datos.labels || [];
    this.chartDatasets = [
      {
        label: 'Precio en ventas',
        data: this.datos.precios || [],
        borderColor: '#428bca',
        backgroundColor: 'rgba(66, 139, 202, 0.1)',
        fill: false,
        tension: 0.2,
        spanGaps: true,
      },
      {
        label: 'Costo en compras',
        data: this.datos.costos || [],
        borderColor: '#d9534f',
        backgroundColor: 'rgba(217, 83, 79, 0.1)',
        fill: false,
        tension: 0.2,
        spanGaps: true,
      },
    ];
  }

  resetDatos(): void {
    this.datos = {};
    this.chartLabels = [];
    this.chartDatasets = [];
  }

  etiquetaTendencia(tendencia: string): string {
    const mapa: Record<string, string> = {
      subiendo: 'Subiendo',
      bajando: 'Bajando',
      estable: 'Estable',
      sin_datos: 'Sin datos',
    };
    return mapa[tendencia] || tendencia;
  }

  claseTendencia(tendencia: string): string {
    const mapa: Record<string, string> = {
      subiendo: 'bg-light-success text-dark',
      bajando: 'bg-light-danger text-dark',
      estable: 'bg-light-warning text-dark',
      sin_datos: 'bg-light text-muted',
    };
    return mapa[tendencia] || 'bg-light text-muted';
  }

  iconoTendencia(tendencia: string): string {
    const mapa: Record<string, string> = {
      subiendo: 'fa-arrow-up',
      bajando: 'fa-arrow-down',
      estable: 'fa-minus',
      sin_datos: 'fa-question-circle',
    };
    return mapa[tendencia] || 'fa-minus';
  }

  private restarseMeses(fecha: string, meses: number): string {
    const d = new Date(fecha + 'T00:00:00');
    d.setMonth(d.getMonth() - meses);
    return d.toISOString().slice(0, 10);
  }
}
