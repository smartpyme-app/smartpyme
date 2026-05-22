import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import * as moment from 'moment';

import { Mesa, RestauranteService } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  standalone: false,
  selector: 'app-cuenta-mesa',
  templateUrl: './cuenta-mesa.component.html',
  styleUrls: ['./cuenta-mesa.component.css']
})
export class CuentaMesaComponent implements OnInit {
  sesion: any = null;
  loading = true;
  enviandoComanda = false;
  solicitandoCuenta = false;
  reactivandoConsumo = false;
  editandoItemId: number | null = null;
  editCantidad = 1;
  editNotas = '';

  mostrarModalCuenta = false;
  modoCuenta: 'completo' | 'dividir' = 'completo';
  tipoDivision: 'equitativa' | 'por_items' = 'equitativa';
  numPagadores = 2;
  /** orden_detalle_id → número de persona → cantidad a cargar en esa pre-cuenta */
  cantidadesPagadorPorItem: Record<number, Record<number, number>> = {};
  /** Último N de comensales usado al armar la grilla (evita reinicios al teclear). */
  private ultimaMatrizNumPagadores = 0;

  mostrarModalEliminar = false;
  itemEliminar: any = null;
  motivoEliminarCodigo = 'error';
  motivoEliminarDetalle = '';
  eliminandoItem = false;

  mostrarModalTraslado = false;
  mesasParaTraslado: Mesa[] = [];
  mesaTrasladoDestinoId: number | null = null;
  itemsTrasladoIds: number[] = [];
  trasladando = false;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    private apiService: ApiService
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

  puedeAutorizarOperacionesRestaurante(): boolean {
    const t = String(this.apiService.auth_user()?.tipo || '').toLowerCase().trim();
    return ['administrador', 'admin', 'gerente'].includes(t);
  }

  itemFueEnviado(item: any): boolean {
    return !!(item?.enviado_cocina || item?.enviado_barra);
  }

  normalizarDestinoProducto(p: any): string {
    const d = String(p?.destino_comanda || 'cocina').toLowerCase().trim();
    if (d === 'barra' || d === 'ambos') {
      return d;
    }
    return 'cocina';
  }

  /** Hay envío pendiente para cocina y/o barra según producto. */
  itemPendienteDeEnvio(item: any): boolean {
    const p = item?.producto;
    if (!p?.genera_comanda) {
      return false;
    }
    const dest = this.normalizarDestinoProducto(p);
    if (dest === 'cocina') {
      return !item.enviado_cocina;
    }
    if (dest === 'barra') {
      return !item.enviado_barra;
    }
    return !item.enviado_cocina || !item.enviado_barra;
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

  get puedeOperarOrden(): boolean {
    return !!this.sesion && ['abierta', 'pre_cuenta'].includes(this.sesion.estado);
  }

  reactivarConsumo(): void {
    if (!this.sesionId || this.sesion?.estado !== 'pre_cuenta') {
      return;
    }
    this.reactivandoConsumo = true;
    this.restauranteService.reactivarConsumoSesion(this.sesionId).subscribe({
      next: (sesion) => {
        this.sesion = sesion;
        this.reactivandoConsumo = false;
        this.alertService.success('Listo', 'Puede seguir agregando productos a la cuenta.');
      },
      error: (err) => {
        this.alertService.error(err);
        this.reactivandoConsumo = false;
      }
    });
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

  abrirModalEliminar(item: any): void {
    if (this.itemFueEnviado(item) && !this.puedeAutorizarOperacionesRestaurante()) {
      this.alertService.warning(
        'Requiere autorización',
        'Este producto ya fue enviado. Inicie sesión con un usuario administrador o gerente para anularlo.'
      );
      return;
    }
    this.itemEliminar = item;
    this.motivoEliminarCodigo = 'error';
    this.motivoEliminarDetalle = '';
    this.mostrarModalEliminar = true;
  }

  cerrarModalEliminar(): void {
    if (this.eliminandoItem) return;
    this.mostrarModalEliminar = false;
    this.itemEliminar = null;
  }

  confirmarEliminar(): void {
    if (!this.itemEliminar?.id) return;
    /** Ventana abierta en el clic del usuario: si se abre dentro del subscribe, el navegador suele bloquear el popup. */
    const printWin = window.open('', '_blank', 'width=400,height=600');
    if (!printWin) {
      this.alertService.warning(
        'Ventana bloqueada',
        'Permita ventanas emergentes para este sitio; sin eso no se puede abrir la comanda de eliminación.'
      );
    }
    this.eliminandoItem = true;
    this.restauranteService
      .eliminarItemSesion(this.sesionId, this.itemEliminar.id, {
        motivo_codigo: this.motivoEliminarCodigo,
        motivo_detalle: this.motivoEliminarDetalle || undefined
      })
      .subscribe({
        next: (res) => {
          this.eliminandoItem = false;
          this.mostrarModalEliminar = false;
          this.itemEliminar = null;
          this.cargarSesion();
          this.alertService.success('Ítem eliminado', 'Registro guardado para control interno.');
          const ce = res?.comanda_eliminacion;
          const comandaId = ce?.id != null ? Number(ce.id) : null;
          if (comandaId && printWin && !printWin.closed) {
            this.restauranteService.imprimirComanda(comandaId).subscribe({
              next: (html) => {
                printWin.document.open();
                printWin.document.write(html);
                printWin.document.close();
                printWin.focus();
              },
              error: (err) => {
                printWin.close();
                this.alertService.error(err);
              }
            });
          } else if (printWin && !printWin.closed) {
            printWin.close();
          }
        },
        error: (err) => {
          if (printWin && !printWin.closed) {
            printWin.close();
          }
          this.alertService.error(err);
          this.eliminandoItem = false;
        }
      });
  }

  abrirModalTraslado(): void {
    if (!this.puedeAutorizarOperacionesRestaurante()) {
      this.alertService.warning('Autorización', 'Solo usuarios administrador o gerente pueden trasladar consumos entre mesas.');
      return;
    }
    const allItems = this.sesion?.orden_detalle || [];
    if (allItems.length === 0) {
      this.alertService.warning('Sin ítems', 'No hay líneas en la cuenta para trasladar.');
      return;
    }
    this.itemsTrasladoIds = allItems.map((i: any) => i.id);
    this.mesaTrasladoDestinoId = null;
    this.restauranteService.getMesas({ activo: true }).subscribe({
      next: (mesas) => {
        const mid = this.sesion?.mesa_id;
        this.mesasParaTraslado = (mesas || []).filter(
          (m) => m.id !== mid && m.estado === 'ocupada' && m.sesion_activa
        );
        if (this.mesasParaTraslado.length === 0) {
          this.alertService.warning(
            'Mesa destino',
            'No hay otras mesas con cuenta abierta. Abra la sesión en la mesa destino antes de trasladar.'
          );
          return;
        }
        this.mostrarModalTraslado = true;
      },
      error: (err) => this.alertService.error(err)
    });
  }

  cerrarModalTraslado(): void {
    if (this.trasladando) return;
    this.mostrarModalTraslado = false;
  }

  toggleTrasladoItem(id: number): void {
    const set = new Set(this.itemsTrasladoIds);
    if (set.has(id)) {
      set.delete(id);
    } else {
      set.add(id);
    }
    this.itemsTrasladoIds = Array.from(set);
  }

  confirmarTraslado(): void {
    if (!this.mesaTrasladoDestinoId || this.itemsTrasladoIds.length === 0) {
      this.alertService.warning('Datos incompletos', 'Seleccione mesa destino y al menos un ítem.');
      return;
    }
    this.trasladando = true;
    this.restauranteService
      .trasladarItems(this.sesionId, {
        mesa_destino_id: this.mesaTrasladoDestinoId,
        orden_detalle_ids: this.itemsTrasladoIds
      })
      .subscribe({
        next: () => {
          this.trasladando = false;
          this.mostrarModalTraslado = false;
          this.cargarSesion();
          this.alertService.success('Traslado', 'Los ítems se movieron a la mesa destino.');
        },
        error: (err) => {
          this.alertService.error(err);
          this.trasladando = false;
        }
      });
  }

  get itemsPendientes(): any[] {
    const items = this.sesion?.orden_detalle || [];
    return items.filter((i: any) => this.itemPendienteDeEnvio(i));
  }

  get hayItemsPendientes(): boolean {
    return this.itemsPendientes.length > 0;
  }

  private imprimirComandasSecuencial(ids: number[], index: number): void {
    if (index >= ids.length) {
      return;
    }
    this.restauranteService.imprimirComanda(ids[index]).subscribe({
      next: (html) => {
        const w = window.open('', '_blank', 'width=400,height=600');
        if (w) {
          w.document.write(html);
          w.document.close();
          w.focus();
        }
        setTimeout(() => this.imprimirComandasSecuencial(ids, index + 1), 400);
      },
      error: (err) => this.alertService.error(err)
    });
  }

  enviarACocina(): void {
    this.enviandoComanda = true;
    this.restauranteService.enviarComanda(this.sesionId).subscribe({
      next: (res: any) => {
        this.enviandoComanda = false;
        this.cargarSesion();
        const list = res?.comandas || [];
        const msg =
          list.length > 1
            ? 'Se generaron comandas para cocina y/o barra.'
            : 'Comanda enviada.';
        this.alertService.success('Enviado', msg);
        const ids = list.map((c: any) => c?.id).filter((x: any) => !!x);
        if (ids.length) {
          this.imprimirComandasSecuencial(ids, 0);
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
    this.numPagadores = Math.min(20, Math.max(2, Number(this.sesion?.num_comensales) || 2));
    this.initMatrizDivisionPorItems();
  }

  private roundQty(x: number): number {
    return Math.round(x * 100) / 100;
  }

  private initMatrizDivisionPorItems(): void {
    this.cantidadesPagadorPorItem = {};
    const items = this.sesion?.orden_detalle || [];
    let n = Math.floor(Number(this.numPagadores));
    if (!Number.isFinite(n) || n < 2) {
      n = 2;
    }
    if (n > 20) {
      n = 20;
    }
    this.numPagadores = n;
    this.ultimaMatrizNumPagadores = n;
    items.forEach((i: any) => {
      const id = Number(i.id);
      this.cantidadesPagadorPorItem[id] = {};
      for (let p = 1; p <= n; p++) {
        this.cantidadesPagadorPorItem[id][p] = p === 1 ? this.roundQty(Number(i.cantidad)) : 0;
      }
    });
  }

  onNumPagadoresCuentaBlur(): void {
    let v = Math.floor(Number(this.numPagadores));
    if (!Number.isFinite(v) || v < 2) {
      v = 2;
    }
    if (v > 20) {
      v = 20;
    }
    this.numPagadores = v;
    if (this.mostrarModalCuenta && this.modoCuenta === 'dividir' && this.tipoDivision === 'por_items' && v !== this.ultimaMatrizNumPagadores) {
      this.initMatrizDivisionPorItems();
    }
  }

  onNumPagadoresModelChange(): void {
    const v = Math.floor(Number(this.numPagadores));
    if (!Number.isFinite(v) || v < 2 || v > 20) {
      return;
    }
    if (this.mostrarModalCuenta && this.modoCuenta === 'dividir' && this.tipoDivision === 'por_items' && v !== this.ultimaMatrizNumPagadores) {
      this.initMatrizDivisionPorItems();
    }
  }

  onModoCuentaChange(): void {
    if (this.modoCuenta === 'dividir' && this.tipoDivision === 'por_items') {
      this.initMatrizDivisionPorItems();
    }
  }

  onTipoDivisionCuentaChange(): void {
    if (this.tipoDivision === 'por_items') {
      this.initMatrizDivisionPorItems();
    }
  }

  private validarMatrizDivisionPorItems(): boolean {
    const items = this.sesion?.orden_detalle || [];
    const n = Math.min(20, Math.max(2, Math.floor(Number(this.numPagadores)) || 2));
    for (const item of items) {
      let sum = 0;
      const row = this.cantidadesPagadorPorItem[Number(item.id)] || {};
      for (let p = 1; p <= n; p++) {
        sum += Number(row[p] || 0);
      }
      const tot = this.roundQty(Number(item.cantidad));
      sum = Math.round(sum * 100) / 100;
      if (Math.abs(sum - tot) > 0.02) {
        this.alertService.warning(
          'Cantidades',
          `«${item.producto?.nombre || 'Producto'}»: reparta ${tot} unidades entre las personas (suma actual: ${sum}).`
        );
        return false;
      }
    }
    return true;
  }

  cerrarModalCuenta(): void {
    this.mostrarModalCuenta = false;
  }

  confirmarSolicitarCuenta(): void {
    const body: Record<string, unknown> = {};
    const nPag = Math.min(20, Math.max(2, Math.floor(Number(this.numPagadores)) || 2));

    if (this.modoCuenta === 'dividir') {
      if (this.tipoDivision === 'por_items' && !this.validarMatrizDivisionPorItems()) {
        return;
      }
      const dividir: Record<string, unknown> = {
        tipo: this.tipoDivision,
        num_pagadores: nPag
      };
      if (this.tipoDivision === 'por_items') {
        const asignaciones: { orden_detalle_id: number; pagador_index: number; cantidad: number }[] = [];
        for (const item of this.sesion?.orden_detalle || []) {
          const row = this.cantidadesPagadorPorItem[Number(item.id)] || {};
          for (let p = 1; p <= nPag; p++) {
            const q = Math.round(Number(row[p] || 0) * 10000) / 10000;
            if (q > 0) {
              asignaciones.push({ orden_detalle_id: Number(item.id), pagador_index: p, cantidad: q });
            }
          }
        }
        dividir['asignaciones'] = asignaciones;
      }
      body['dividir'] = dividir;
    }

    this.solicitandoCuenta = true;
    this.restauranteService.solicitarCuenta(this.sesionId, body).subscribe({
      next: (res) => {
        this.solicitandoCuenta = false;
        this.cerrarModalCuenta();
        this.cargarSesion();
        const esArreglo = Array.isArray(res);
        if (this.modoCuenta === 'completo' && !esArreglo && res?.id != null) {
          this.alertService.success('Pre-cuenta generada', 'Puede imprimirla desde la lista.');
          this.imprimirPreCuenta(Number(res.id));
        } else if (this.modoCuenta === 'dividir') {
          this.alertService.success('Cuenta dividida', `Se generaron ${nPag} pre-cuentas.`);
        }
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
        if (w) {
          w.document.write(html);
          w.document.close();
          w.focus();
        }
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
            propina_monto: data.propina_monto,
            propina_porcentaje_aplicado: data.propina_porcentaje_aplicado,
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
    return items.reduce((sum: number, i: any) => sum + i.cantidad * (i.precio_unitario || 0), 0);
  }

  propinaMontoOrdenAbierta(): number {
    const pct = parseFloat(String(this.apiService.auth_user()?.empresa?.propina_porcentaje ?? '')) || 0;
    if (pct <= 0) {
      return 0;
    }
    return Math.round(this.subtotal() * (pct / 100) * 100) / 100;
  }

  totalConPropinaOrdenAbierta(): number {
    return Math.round((this.subtotal() + this.propinaMontoOrdenAbierta()) * 100) / 100;
  }

  propinaPorcentajeEmpresa(): number {
    return parseFloat(String(this.apiService.auth_user()?.empresa?.propina_porcentaje ?? '')) || 0;
  }
}
