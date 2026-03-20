import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import * as moment from 'moment';

import { RestauranteService } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-cuenta-mesa',
  templateUrl: './cuenta-mesa.component.html',
  styleUrls: ['./cuenta-mesa.component.css']
})
export class CuentaMesaComponent implements OnInit {
  sesion: any = null;
  loading = true;
  enviandoComanda = false;
  solicitandoCuenta = false;
  editandoItemId: number | null = null;
  editCantidad = 1;
  editNotas = '';

  mostrarModalCuenta = false;
  modoCuenta: 'completo' | 'dividir' = 'completo';
  tipoDivision: 'equitativa' | 'por_items' = 'equitativa';
  numPagadores = 2;
  asignacionesPorItem: Record<number, number> = {};

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private restauranteService: RestauranteService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (!id) {
      this.router.navigate(['/restaurante']);
      return;
    }
    this.cargarSesion();
  }

  get sesionId(): number {
    return this.sesion?.id ?? 0;
  }

  cargarSesion(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (!id) return;
    this.loading = true;
    this.restauranteService.getSesion(+id).subscribe({
      next: (sesion) => {
        this.sesion = sesion;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
        this.router.navigate(['/restaurante']);
      }
    });
  }

  tiempoTranscurrido(): string {
    if (!this.sesion?.opened_at) return '-';
    return moment(this.sesion.opened_at).fromNow(true);
  }

  volver(): void {
    this.router.navigate(['/restaurante']);
  }

  onProductoSelect(producto: any): void {
    this.restauranteService.agregarItem(this.sesionId, {
      producto_id: producto.id,
      cantidad: 1,
      notas: ''
    }).subscribe({
      next: () => {
        this.cargarSesion();
        this.alertService.success('Producto agregado', `${producto.nombre} añadido a la orden.`);
      },
      error: (err) => this.alertService.error(err)
    });
  }

  iniciarEditar(item: any): void {
    this.editandoItemId = item.id;
    this.editCantidad = item.cantidad;
    this.editNotas = item.notas || '';
  }

  guardarEdicion(): void {
    if (!this.editandoItemId) return;
    this.restauranteService.actualizarItem(this.sesionId, this.editandoItemId, {
      cantidad: this.editCantidad,
      notas: this.editNotas || undefined
    }).subscribe({
      next: () => {
        this.editandoItemId = null;
        this.cargarSesion();
      },
      error: (err) => this.alertService.error(err)
    });
  }

  cancelarEdicion(): void {
    this.editandoItemId = null;
  }

  eliminarItem(item: any): void {
    if (!confirm(`¿Eliminar "${item.producto?.nombre}" de la orden?`)) return;
    this.restauranteService.eliminarItem(this.sesionId, item.id).subscribe({
      next: () => {
        this.cargarSesion();
        this.alertService.success('Ítem eliminado', 'Se ha quitado de la orden.');
      },
      error: (err) => this.alertService.error(err)
    });
  }

  get itemsPendientes(): any[] {
    const items = this.sesion?.orden_detalle || [];
    return items.filter((i: any) => !i.enviado_cocina);
  }

  get hayItemsPendientes(): boolean {
    return this.itemsPendientes.length > 0;
  }

  enviarACocina(): void {
    this.enviandoComanda = true;
    this.restauranteService.enviarComanda(this.sesionId).subscribe({
      next: (comanda) => {
        this.enviandoComanda = false;
        this.cargarSesion();
        this.alertService.success('Comanda enviada', 'Los ítems han sido enviados a cocina.');
        if (comanda?.id) {
          this.restauranteService.imprimirComanda(comanda.id).subscribe({
            next: (html) => {
              const w = window.open('', '_blank', 'width=400,height=600');
              if (w) { w.document.write(html); w.document.close(); w.focus(); }
            }
          });
        }
      },
      error: (err) => {
        this.alertService.error(err);
        this.enviandoComanda = false;
      }
    });
  }

  abrirModalSolicitarCuenta(): void {
    const items = this.sesion?.orden_detalle || [];
    if (items.length === 0) {
      this.alertService.warning('Orden vacía', 'Agregue productos antes de solicitar la cuenta.');
      return;
    }
    this.mostrarModalCuenta = true;
    this.modoCuenta = 'completo';
    this.tipoDivision = 'equitativa';
    this.numPagadores = Math.min(2, Math.max(2, this.sesion?.num_comensales || 2));
    this.asignacionesPorItem = {};
    (items as any[]).forEach((i: any) => { this.asignacionesPorItem[i.id] = 1; });
  }

  cerrarModalCuenta(): void {
    this.mostrarModalCuenta = false;
  }

  confirmarSolicitarCuenta(): void {
    this.solicitandoCuenta = true;
    this.restauranteService.solicitarCuenta(this.sesionId).subscribe({
      next: (preCuenta) => {
        if (this.modoCuenta === 'completo') {
          this.solicitandoCuenta = false;
          this.cerrarModalCuenta();
          this.cargarSesion();
          this.alertService.success('Pre-cuenta generada', 'Puede imprimirla desde la lista.');
          if (preCuenta?.id) {
            this.imprimirPreCuenta(preCuenta.id);
          }
        } else {
          this.dividirYContinuar(preCuenta?.id);
        }
      },
      error: (err) => {
        this.alertService.error(err);
        this.solicitandoCuenta = false;
      }
    });
  }

  private dividirYContinuar(preCuentaId: number | undefined): void {
    if (!preCuentaId) {
      this.solicitandoCuenta = false;
      this.cerrarModalCuenta();
      this.cargarSesion();
      return;
    }
    const payload: any = { tipo: this.tipoDivision, num_pagadores: this.numPagadores };
    if (this.tipoDivision === 'por_items') {
      payload.asignaciones = Object.entries(this.asignacionesPorItem).map(([orden_detalle_id, pagador_index]) => ({
        orden_detalle_id: +orden_detalle_id,
        pagador_index
      }));
    }
    this.restauranteService.dividirCuenta(preCuentaId, payload).subscribe({
      next: () => {
        this.solicitandoCuenta = false;
        this.cerrarModalCuenta();
        this.cargarSesion();
        this.alertService.success('Cuenta dividida', 'Se generaron ' + this.numPagadores + ' pre-cuentas.');
      },
      error: (err) => {
        this.alertService.error(err);
        this.solicitandoCuenta = false;
      }
    });
  }

  imprimirPreCuenta(preCuentaId: number): void {
    this.restauranteService.imprimirPreCuenta(preCuentaId).subscribe({
      next: (html) => {
        const w = window.open('', '_blank', 'width=400,height=600');
        if (w) { w.document.write(html); w.document.close(); w.focus(); }
      },
      error: (err) => this.alertService.error(err)
    });
  }

  irAFacturar(preCuentaId: number): void {
    this.restauranteService.prepararFactura(preCuentaId).subscribe({
      next: (data) => {
        const state = {
          preCuentaId: data.pre_cuenta_id,
          sesionId: data.sesion_id,
          preCuentaData: {
            mesa_numero: data.mesa_numero,
            subtotal: data.subtotal,
            total: data.total,
            detalles: data.detalles
          }
        };
        this.router.navigate(['/venta/crear'], {
          queryParams: {
            pre_cuenta: data.pre_cuenta_id,
            sesion: data.sesion_id
          },
          state
        });
      },
      error: (err) => this.alertService.error(err)
    });
  }

  get preCuentas(): any[] {
    return this.sesion?.pre_cuentas ?? this.sesion?.preCuentas ?? [];
  }

  get opcionesPagadores(): number[] {
    return Array.from({ length: Math.min(20, Math.max(2, this.numPagadores)) }, (_, i) => i + 1);
  }

  subtotal(): number {
    const items = this.sesion?.orden_detalle || [];
    return items.reduce((sum: number, i: any) => sum + (i.cantidad * (i.precio_unitario || 0)), 0);
  }
}
