import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { etiquetaEstadoCola } from './creditos-cuotas';
import { puedeFacturarVentaCuota, queryFacturarCuota, queryFacturarVenta } from './creditos-facturar';

@Component({
  selector: 'app-credito-detalle',
  standalone: true,
  imports: [CommonModule, RouterModule, CurrencyPipe],
  templateUrl: './credito-detalle.component.html',
})
export class CreditoDetalleComponent implements OnInit {
  credito: any = null;
  queryFacturarCuota = queryFacturarCuota;
  queryFacturarVenta = queryFacturarVenta;
  puedeFacturarVentaCuota = puedeFacturarVentaCuota;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.apiService.read('creditos-clientes/', id).subscribe({
      next: (credito) => {
        this.credito = credito;
      },
      error: (err) => this.alertService.error(err),
    });
  }

  etiquetaTipo(tipo: string): string {
    if (tipo === 'bien') return 'Bien';
    if (tipo === 'servicio') return 'Servicio';
    if (tipo === 'prestamo') return 'Préstamo';
    return tipo || '-';
  }

  etiquetaEstadoContrato(estado: string): string {
    if (estado === 'activo') return 'Activo';
    if (estado === 'cerrado') return 'Cerrado';
    return estado || '-';
  }

  etiquetaEstadoCuota(cuota: any): string {
    if (this.puedeFacturarVentaCuota(cuota?.venta, true)) {
      return 'Por facturar';
    }
    return etiquetaEstadoCola(cuota?.estado_visible || cuota?.estado);
  }

  claseEstadoCuota(cuota: any): string {
    const estado = this.etiquetaEstadoCuota(cuota);
    if (estado === 'Facturada') return 'bg-success';
    if (estado === 'Por facturar' || estado === 'Vencida') return 'bg-warning';
    return 'bg-secondary';
  }
}
