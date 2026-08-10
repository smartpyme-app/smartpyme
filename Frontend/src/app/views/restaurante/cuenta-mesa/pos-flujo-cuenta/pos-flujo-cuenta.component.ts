import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';

import { AlertService } from '@services/alert.service';
import {
  asignarExclusivo,
  asignarUnidades,
  buildAsignaciones,
  lineaCompleta,
  matrizValida,
  sumaPersonaLinea
} from '../pos/pos-division';

export type EstadoLinea = 'sin_asignar' | 'parcial' | 'completo';

@Component({
  standalone: false,
  selector: 'app-pos-flujo-cuenta',
  templateUrl: './pos-flujo-cuenta.component.html',
  styleUrls: ['./pos-flujo-cuenta.component.css']
})
export class PosFlujoCuentaComponent implements OnChanges {
  @Input() sesion: any = null;
  @Input() visible = false;
  @Input() solicitando = false;

  @Output() cerrado = new EventEmitter<void>();
  @Output() confirmado = new EventEmitter<{
    body: Record<string, unknown>;
    modoCuenta: 'completo' | 'dividir';
    numPagadores: number;
  }>();

  modoCuenta: 'completo' | 'dividir' = 'completo';
  tipoDivision: 'equitativa' | 'por_items' = 'equitativa';
  numPagadores = 2;
  personaActiva = 1;
  matriz: Record<number, Record<number, number>> = {};

  /** Ítem con cantidad > 1 en proceso de partición (mini-prompt de unidades). */
  itemPartir: any = null;
  cantidadPartir = 0;

  constructor(private alertService: AlertService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.modoCuenta = 'completo';
      this.tipoDivision = 'equitativa';
      this.numPagadores = this.clampPagadores(Number(this.sesion?.num_comensales) || 2);
      this.resetMatrizPorItems();
    }
  }

  get items(): any[] {
    return this.sesion?.orden_detalle || [];
  }

  get personas(): number[] {
    return Array.from({ length: this.numPagadores }, (_, i) => i + 1);
  }

  get matrizCompleta(): boolean {
    return matrizValida(
      this.items.map((i: any) => ({ id: Number(i.id), cantidad: Number(i.cantidad) })),
      this.matriz,
      this.numPagadores
    );
  }

  private clampPagadores(n: number): number {
    let v = Math.floor(Number(n));
    if (!Number.isFinite(v) || v < 2) v = 2;
    if (v > 20) v = 20;
    return v;
  }

  private resetMatrizPorItems(): void {
    this.matriz = {};
    this.personaActiva = 1;
    this.itemPartir = null;
    this.cantidadPartir = 0;
  }

  onNumPagadoresChange(delta = 0): void {
    const actual = Number(this.numPagadores);
    const siguiente = this.clampPagadores(actual + delta);
    this.numPagadores = siguiente;
    // El blur del input no debe borrar el reparto si el valor no cambió.
    if (siguiente !== actual) {
      this.resetMatrizPorItems();
    }
  }

  onModoCuentaChange(): void {
    if (this.modoCuenta === 'dividir' && this.tipoDivision === 'por_items') {
      this.resetMatrizPorItems();
    }
  }

  onTipoDivisionChange(): void {
    if (this.tipoDivision === 'por_items') {
      this.resetMatrizPorItems();
    }
  }

  setPersonaActiva(p: number): void {
    this.personaActiva = p;
  }

  onTapItem(item: any): void {
    if (this.tipoDivision !== 'por_items') return;
    const max = Number(item.cantidad);
    if (max === 1) {
      this.matriz = asignarExclusivo(this.matriz, Number(item.id), this.personaActiva, 1, max);
      return;
    }
    this.cantidadPartir = this.cantidadAsignada(item, this.personaActiva);
    this.itemPartir = item;
  }

  confirmarPartir(): void {
    if (!this.itemPartir) return;
    const max = Number(this.itemPartir.cantidad);
    this.matriz = asignarUnidades(this.matriz, Number(this.itemPartir.id), this.personaActiva, Number(this.cantidadPartir) || 0, max);
    this.itemPartir = null;
  }

  stepPartir(delta: number): void {
    if (!this.itemPartir) return;
    const max = Number(this.itemPartir.cantidad);
    const v = Math.round((Number(this.cantidadPartir) + delta) * 100) / 100;
    this.cantidadPartir = Math.max(0, Math.min(max, v));
  }

  cancelarPartir(): void {
    this.itemPartir = null;
  }

  cantidadAsignada(item: any, persona: number): number {
    const row = this.matriz[Number(item.id)] || {};
    return Number(row[persona] || 0);
  }

  sumaAsignada(item: any): number {
    return sumaPersonaLinea(this.matriz, Number(item.id), this.numPagadores);
  }

  estadoLinea(item: any): EstadoLinea {
    const suma = this.sumaAsignada(item);
    if (suma <= 0) return 'sin_asignar';
    return lineaCompleta(this.matriz, Number(item.id), Number(item.cantidad), this.numPagadores) ? 'completo' : 'parcial';
  }

  cerrar(): void {
    if (this.solicitando) return;
    this.cerrado.emit();
  }

  confirmar(): void {
    if (this.solicitando) return;
    const nPag = this.clampPagadores(this.numPagadores);
    const body: Record<string, unknown> = {};

    if (this.modoCuenta === 'dividir') {
      if (this.tipoDivision === 'por_items' && !this.matrizCompleta) {
        this.alertService.warning('Cantidades', 'Reparta cada línea completa entre las personas antes de confirmar.');
        return;
      }
      const dividir: Record<string, unknown> = { tipo: this.tipoDivision, num_pagadores: nPag };
      if (this.tipoDivision === 'por_items') {
        dividir['asignaciones'] = buildAsignaciones(
          this.items.map((i: any) => ({ id: Number(i.id), cantidad: Number(i.cantidad) })),
          this.matriz,
          nPag
        );
      }
      body['dividir'] = dividir;
    }

    this.confirmado.emit({ body, modoCuenta: this.modoCuenta, numPagadores: nPag });
  }
}
