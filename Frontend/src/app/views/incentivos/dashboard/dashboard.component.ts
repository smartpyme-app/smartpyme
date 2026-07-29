import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import {
  IncentivosService,
  VendedorIncentivosDetalle,
  VendedorIncentivosResumen
} from '@services/incentivos.service';
import { ModalManagerService } from '@services/modal-manager.service';

@Component({
  selector: 'app-incentivos-dashboard',
  standalone: false,
  templateUrl: './dashboard.component.html'
})
export class DashboardComponent implements OnInit {
  desde = '';
  hasta = '';
  loading = false;
  loadingDetalle = false;
  vendedores: VendedorIncentivosResumen[] = [];
  detalle: VendedorIncentivosDetalle | null = null;
  vendedorSeleccionado: VendedorIncentivosResumen | null = null;
  modalRef?: BsModalRef;

  constructor(
    private incentivosService: IncentivosService,
    private alertService: AlertService,
    private modalManager: ModalManagerService
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
    this.vendedorSeleccionado = null;

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

  verDetalle(template: TemplateRef<unknown>, vendedor: VendedorIncentivosResumen): void {
    this.vendedorSeleccionado = vendedor;
    this.detalle = null;
    this.loadingDetalle = true;
    this.modalRef = this.modalManager.openModal(template, {
      class: 'modal-lg modal-dialog-scrollable',
      backdrop: true
    });

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

  cerrarModal(): void {
    this.modalManager.closeModal(this.modalRef);
    this.detalle = null;
    this.vendedorSeleccionado = null;
    this.loadingDetalle = false;
  }

  totalGeneral(total: { comisiones: number; bonos_aprobados_o_pagados: number }): number {
    return (total?.comisiones ?? 0) + (total?.bonos_aprobados_o_pagados ?? 0);
  }

  estadoBonoClass(estado: string): string {
    switch (estado) {
      case 'pendiente':
        return 'bg-warning text-dark';
      case 'aprobado':
        return 'bg-info';
      case 'pagado':
        return 'bg-success';
      default:
        return 'bg-secondary';
    }
  }

  progresoPct(actual: number, meta: number): number {
    if (!meta || meta <= 0) {
      return 0;
    }
    return Math.min(100, Math.round((actual / meta) * 100));
  }
}
