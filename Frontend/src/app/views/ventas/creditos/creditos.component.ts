import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { puedeCrearCredito } from './creditos-acceso';
import { etiquetaEstadoCola } from './creditos-cuotas';

@Component({
  selector: 'app-creditos',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './creditos.component.html',
})
export class CreditosComponent implements OnInit {
  creditos: any[] = [];
  cola: any[] = [];
  filtroEstado = '';
  puedeCrear = false;
  etiquetaEstadoCola = etiquetaEstadoCola;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
  ) {}

  ngOnInit(): void {
    this.puedeCrear = puedeCrearCredito(this.apiService.isVentasLimitado());
    this.cargarContratos();
    this.cargarCola();
  }

  cargarContratos(): void {
    this.apiService.getAll('creditos-clientes').subscribe({
      next: (res) => {
        this.creditos = res?.data ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  cargarCola(): void {
    const filtros: any = {};
    if (this.filtroEstado) {
      filtros.estado = this.filtroEstado;
    }
    this.apiService.getAll('creditos-clientes/cola', filtros).subscribe({
      next: (res) => {
        this.cola = res?.data ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }
}
