import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import {
  IncentivosService,
  VendedorIncentivosDetalle,
  VendedorIncentivosResumen
} from '@services/incentivos.service';

@Component({
  selector: 'app-incentivos-dashboard',
  templateUrl: './dashboard.component.html'
})
export class DashboardComponent implements OnInit {
  desde = '';
  hasta = '';
  loading = false;
  loadingDetalle = false;
  vendedores: VendedorIncentivosResumen[] = [];
  detalle: VendedorIncentivosDetalle | null = null;
  vendedorSeleccionadoId: number | null = null;

  constructor(
    private incentivosService: IncentivosService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    const hoy = new Date();
    const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    this.desde = this.toInputDate(inicioMes);
    this.hasta = this.toInputDate(hoy);
    this.loadVendedores();
  }

  private toInputDate(date: Date): string {
    return date.toISOString().substring(0, 10);
  }

  loadVendedores(): void {
    if (!this.desde || !this.hasta) {
      this.alertService.warning('Atención', 'Seleccione el rango de fechas.');
      return;
    }

    this.loading = true;
    this.detalle = null;
    this.vendedorSeleccionadoId = null;

    this.incentivosService.listarVendedores(this.desde, this.hasta).subscribe({
      next: (response) => {
        this.vendedores = response.data ?? [];
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  verDetalle(vendedor: VendedorIncentivosResumen): void {
    this.vendedorSeleccionadoId = vendedor.id_vendedor;
    this.loadingDetalle = true;

    this.incentivosService.detalleVendedor(vendedor.id_vendedor, this.desde, this.hasta).subscribe({
      next: (response) => {
        this.detalle = response.data ?? null;
        this.loadingDetalle = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loadingDetalle = false;
      }
    });
  }

  totalGeneral(total: { comisiones: number; bonos_aprobados_o_pagados: number }): number {
    return (total?.comisiones ?? 0) + (total?.bonos_aprobados_o_pagados ?? 0);
  }
}
