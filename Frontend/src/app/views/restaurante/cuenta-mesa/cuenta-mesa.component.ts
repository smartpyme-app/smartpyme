import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import * as moment from 'moment';

import { Mesa, RestauranteService } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { nombreLineaOrden as nombreLineaOrdenFn } from './pos/pos-menu-nav';
import { MENSAJE_CONFIRMAR_CERRAR_MESA, puedeCerrarMesaRestaurante } from '../restaurante-roles.util';

@Component({
  standalone: false,
  selector: 'app-cuenta-mesa',
  templateUrl: './cuenta-mesa.component.html',
  styleUrls: ['./cuenta-mesa.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CuentaMesaComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);
  sesion: any = null;
  loading = true;
  guardandoComensales = false;
  enviandoComanda = false;
  solicitandoCuenta = false;
  reactivandoConsumo = false;
  cerrandoMesa = false;
  editandoItemId: number | null = null;
  editCantidad = 1;
  editNotas = '';

  mostrarModalCuenta = false;

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

  productoSheet: any = null;
  mostrarSheetAgregar = false;
  enviandoAgregar = false;

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

  /** Etiqueta POS: "Mesa 5 — Terraza" (o solo el número si no hay zona). */
  get mesaConZonaLabel(): string {
    const numero = this.sesion?.mesa?.numero;
    if (numero == null || numero === '') {
      return 'Mesa';
    }
    const zona =
      this.sesion?.mesa?.zona_restaurante?.nombre ||
      this.sesion?.mesa?.zona ||
      '';
    const zonaTrim = String(zona).trim();
    return zonaTrim ? `Mesa ${numero} — ${zonaTrim}` : `Mesa ${numero}`;
  }

  puedeAutorizarOperacionesRestaurante(): boolean {
    const t = String(this.apiService.auth_user()?.tipo || '').toLowerCase().trim();
    return ['administrador', 'admin', 'gerente'].includes(t);
  }

  /** SP-2158 */
  puedeCerrarMesa(): boolean {
    return puedeCerrarMesaRestaurante(this.apiService.auth_user()?.tipo);
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
    this.cdr.markForCheck();
    this.restauranteService
      .getSesion(+id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
      next: (sesion) => {
        this.sesion = sesion;
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
        this.cdr.markForCheck();
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

  cambiarComensales(delta: number): void {
    if (!this.sesion || this.guardandoComensales || !this.puedeOperarOrden) {
      return;
    }
    const actual = Math.max(1, Number(this.sesion.num_comensales) || 1);
    const siguiente = Math.min(99, Math.max(1, actual + delta));
    if (siguiente === actual) {
      return;
    }
    this.guardandoComensales = true;
    this.sesion = { ...this.sesion, num_comensales: siguiente };
    this.cdr.markForCheck();
    this.restauranteService
      .actualizarSesion(this.sesionId, { num_comensales: siguiente })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (sesion) => {
          this.sesion = { ...this.sesion, ...sesion };
          this.guardandoComensales = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.sesion = { ...this.sesion, num_comensales: actual };
          this.guardandoComensales = false;
          this.alertService.error(err);
          this.cdr.markForCheck();
        }
      });
  }

  cerrarMesa(): void {
    if (!this.sesionId || !this.puedeCerrarMesa() || !this.puedeOperarOrden || this.cerrandoMesa) {
      return;
    }
    const msg = MENSAJE_CONFIRMAR_CERRAR_MESA;
    if (!confirm(msg)) {
      return;
    }
    this.cerrandoMesa = true;
    this.cdr.markForCheck();
    this.restauranteService
      .cerrarSesion(this.sesionId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.cerrandoMesa = false;
          this.alertService.success('Mesa cerrada', 'La mesa quedó libre en el mapa.');
          this.router.navigate(['/restaurante']);
        },
        error: (err) => {
          this.alertService.error(err);
          this.cerrandoMesa = false;
          this.cdr.markForCheck();
        },
      });
  }

  reactivarConsumo(): void {
    if (!this.sesionId || this.sesion?.estado !== 'pre_cuenta') {
      return;
    }
    this.reactivandoConsumo = true;
    this.cdr.markForCheck();
    this.restauranteService
      .reactivarConsumoSesion(this.sesionId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
      next: (sesion) => {
        this.sesion = sesion;
        this.reactivandoConsumo = false;
        this.alertService.success('Listo', 'Puede seguir agregando productos a la cuenta.');
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.reactivandoConsumo = false;
        this.cdr.markForCheck();
      }
    });
  }

  onProductoCatalogo(producto: any): void {
    this.productoSheet = producto;
    this.mostrarSheetAgregar = true;
    this.cdr.markForCheck();
  }

  onCancelarSheetAgregar(): void {
    if (this.enviandoAgregar) {
      return;
    }
    this.mostrarSheetAgregar = false;
    this.productoSheet = null;
    this.cdr.markForCheck();
  }

  nombreLineaOrden(item: { producto?: { nombre?: string } | null; presentacion?: { nombre_comercial?: string } | null }): string {
    return nombreLineaOrdenFn(item);
  }

  onConfirmarAgregar(payload: { producto_id: number; id_presentacion?: number | null; cantidad: number; notas: string }): void {
    if (this.enviandoAgregar) {
      return;
    }
    this.enviandoAgregar = true;
    this.cdr.markForCheck();
    this.restauranteService
      .agregarItem(this.sesionId, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
      next: () => {
        const nombre = this.productoSheet?.nombre_mostrar || this.productoSheet?.nombre;
        this.enviandoAgregar = false;
        this.mostrarSheetAgregar = false;
        this.productoSheet = null;
        this.cargarSesion();
        if (nombre) {
          this.alertService.success('Producto agregado', `${nombre} añadido a la orden.`);
        }
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.enviandoAgregar = false;
        this.alertService.error(err);
        this.cdr.markForCheck();
      }
    });
  }

  iniciarEditar(item: any): void {
    this.editandoItemId = item.id;
    this.editCantidad = item.cantidad;
    this.editNotas = item.notas || '';
    this.cdr.markForCheck();
  }

  guardarEdicion(): void {
    if (!this.editandoItemId) return;
    this.restauranteService
      .actualizarItem(this.sesionId, this.editandoItemId, {
      cantidad: this.editCantidad,
      notas: this.editNotas || undefined
    })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
      next: () => {
        this.editandoItemId = null;
        this.cargarSesion();
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.cdr.markForCheck();
      }
    });
  }

  cancelarEdicion(): void {
    this.editandoItemId = null;
    this.cdr.markForCheck();
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
    this.cdr.markForCheck();
  }

  cerrarModalEliminar(): void {
    if (this.eliminandoItem) return;
    this.mostrarModalEliminar = false;
    this.itemEliminar = null;
    this.cdr.markForCheck();
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
    this.cdr.markForCheck();
    this.restauranteService
      .eliminarItemSesion(this.sesionId, this.itemEliminar.id, {
        motivo_codigo: this.motivoEliminarCodigo,
        motivo_detalle: this.motivoEliminarDetalle || undefined
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.eliminandoItem = false;
          this.mostrarModalEliminar = false;
          this.itemEliminar = null;
          this.cargarSesion();
          this.alertService.success('Ítem eliminado', 'Registro guardado para control interno.');
          this.cdr.markForCheck();
          const ce = res?.comanda_eliminacion;
          const comandaId = ce?.id != null ? Number(ce.id) : null;
          if (comandaId && printWin && !printWin.closed) {
            this.restauranteService
              .imprimirComanda(comandaId)
              .pipe(takeUntilDestroyed(this.destroyRef))
              .subscribe({
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
          this.cdr.markForCheck();
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
    this.cdr.markForCheck();
    this.restauranteService
      .getMesas({ activo: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
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
          this.cdr.markForCheck();
          return;
        }
        this.mostrarModalTraslado = true;
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.cdr.markForCheck();
      }
    });
  }

  cerrarModalTraslado(): void {
    if (this.trasladando) return;
    this.mostrarModalTraslado = false;
    this.cdr.markForCheck();
  }

  toggleTrasladoItem(id: number): void {
    const set = new Set(this.itemsTrasladoIds);
    if (set.has(id)) {
      set.delete(id);
    } else {
      set.add(id);
    }
    this.itemsTrasladoIds = Array.from(set);
    this.cdr.markForCheck();
  }

  confirmarTraslado(): void {
    if (!this.mesaTrasladoDestinoId || this.itemsTrasladoIds.length === 0) {
      this.alertService.warning('Datos incompletos', 'Seleccione mesa destino y al menos un ítem.');
      return;
    }
    this.trasladando = true;
    this.cdr.markForCheck();
    this.restauranteService
      .trasladarItems(this.sesionId, {
        mesa_destino_id: this.mesaTrasladoDestinoId,
        orden_detalle_ids: this.itemsTrasladoIds
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.trasladando = false;
          this.mostrarModalTraslado = false;
          this.cargarSesion();
          this.alertService.success('Traslado', 'Los ítems se movieron a la mesa destino.');
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.alertService.error(err);
          this.trasladando = false;
          this.cdr.markForCheck();
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
    this.restauranteService
      .imprimirComanda(ids[index])
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
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
    this.cdr.markForCheck();
    this.restauranteService
      .enviarComanda(this.sesionId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
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
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.enviandoComanda = false;
        this.cdr.markForCheck();
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
    this.cdr.markForCheck();
  }

  cerrarModalCuenta(): void {
    if (this.solicitandoCuenta) return;
    this.mostrarModalCuenta = false;
    this.cdr.markForCheck();
  }

  confirmarSolicitarCuenta(payload: { body: Record<string, unknown>; modoCuenta: 'completo' | 'dividir'; numPagadores: number }): void {
    this.solicitandoCuenta = true;
    this.cdr.markForCheck();
    this.restauranteService
      .solicitarCuenta(this.sesionId, payload.body)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
      next: (res) => {
        this.solicitandoCuenta = false;
        this.cerrarModalCuenta();
        this.cargarSesion();
        const esArreglo = Array.isArray(res);
        if (payload.modoCuenta === 'completo' && !esArreglo && res?.id != null) {
          this.alertService.success('Pre-cuenta generada', 'Puede imprimirla desde la lista.');
          this.imprimirPreCuenta(Number(res.id));
        } else if (payload.modoCuenta === 'dividir') {
          this.alertService.success('Cuenta dividida', `Se generaron ${payload.numPagadores} pre-cuentas.`);
        }
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.solicitandoCuenta = false;
        this.cdr.markForCheck();
      }
    });
  }

  imprimirPreCuenta(preCuentaId: number): void {
    this.restauranteService
      .imprimirPreCuenta(preCuentaId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
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
    this.restauranteService
      .prepararFactura(preCuentaId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
      next: (data) => {
        const state = {
          preCuentaId: data.pre_cuenta_id,
          sesionId: data.sesion_id,
          preCuentaData: {
            mesa_numero: data.mesa_numero,
            subtotal: data.subtotal,
            impuesto: data.impuesto,
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

  ivaPorcentajeEmpresa(): number {
    return parseFloat(String(this.apiService.auth_user()?.empresa?.iva ?? '')) || 0;
  }

  impuestoMontoOrdenAbierta(): number {
    const pctBase = this.ivaPorcentajeEmpresa();
    if (pctBase <= 0) {
      return 0;
    }
    return Math.round(this.subtotal() * (pctBase / 100) * 100) / 100;
  }

  totalConImpuestoOrdenAbierta(): number {
    return Math.round((this.subtotal() + this.impuestoMontoOrdenAbierta()) * 100) / 100;
  }

  totalGeneralOrdenAbierta(): number {
    return Math.round((this.totalConImpuestoOrdenAbierta() + this.propinaMontoOrdenAbierta()) * 100) / 100;
  }
}
