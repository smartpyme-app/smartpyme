import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { etiquetaEstadoCola } from './creditos-cuotas';
import { queryFacturarCuota } from './creditos-facturar';

@Component({
  selector: 'app-credito-detalle',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './credito-detalle.component.html',
})
export class CreditoDetalleComponent implements OnInit {
  credito: any = null;
  etiquetaEstadoCola = etiquetaEstadoCola;
  queryFacturarCuota = queryFacturarCuota;

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
}
