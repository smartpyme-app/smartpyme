import { Component, Input, OnChanges, OnInit, SimpleChanges, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-presentaciones',
  templateUrl: './producto-presentaciones.component.html',
  styleUrls: ['./producto-presentaciones.component.scss'],
})
export class ProductoPresentacionesComponent implements OnInit, OnChanges {

  /** El componente padre (ProductoComponent) pasa el producto ya cargado. */
  @Input() producto: any = null;

  public presentaciones: any[] = [];
  public unidades: any[] = [];

  public saving = false;

  /** null = modo creación | number = ID de la presentación que se está editando */
  public editandoId: number | null = null;

  modalRef!: BsModalRef;

  /**
   * Opción A: presentación MAYOR que la unidad base.
   * Ej: 1 Caja = 30 Ampollas → inputMultiplicador = 30, factor = 30
   */
  public inputMultiplicador: number | null = 1;

  /**
   * Opción B: presentación MENOR que la unidad base (fracción).
   * Ej: 100 Guantes = 1 Caja → inputDivisor = 100, factor = 0.010000
   */
  public inputDivisor: number | null = null;

  /** Controla cuál ecuación se muestra al usuario en el modal */
  public modoEquivalencia: 'multiplicador' | 'divisor' = 'multiplicador';

  public form: any = {
    id_unidad_medida: '',
    nombre_comercial: '',
    factor_conversion: 1.000000,
    precio_venta: 0,
    codigo_barras: '',
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
  ) {}

  ngOnInit(): void {
    this.loadUnidades();
  }

  ngOnChanges(changes: SimpleChanges): void {
    // Cuando el padre actualiza el objeto producto, sincronizamos presentaciones
    if (changes['producto'] && this.producto) {
      this.presentaciones = this.producto.presentaciones || [];
    }
  }

  // ─── Carga de datos ─────────────────────────────────────────────────────────

  private loadUnidades(): void {
    this.apiService.getAll('unidades').subscribe(
      (data: any[]) => { this.unidades = data; },
      (error: any) => { this.alertService.error(error); }
    );
  }

  // ─── Nombre de la unidad base ────────────────────────────────────────────────

  /** Devuelve el nombre de la unidad base del producto (para mostrar en la ecuación). */
  get nombreUnidadBase(): string {
    return this.producto?.unidad?.nombre || this.producto?.medida || 'unidad(es) base';
  }

  // ─── Lógica del factor de conversión ─────────────────────────────────────────

  /**
   * Recalcula form.factor_conversion según el input que el usuario usó.
   * Solo uno de los dos inputs puede estar activo a la vez.
   *
   * @param origen 'multiplicador' | 'divisor'
   */
  calcularFactor(origen: 'multiplicador' | 'divisor'): void {
    if (origen === 'multiplicador') {
      // Limpiar el divisor y usar el multiplicador como factor directo
      this.inputDivisor = null;
      const val = Number(this.inputMultiplicador);
      this.form.factor_conversion = (!isNaN(val) && val > 0)
        ? Number(val.toFixed(6))
        : 1.000000;
    } else {
      // Limpiar el multiplicador e invertir el divisor
      this.inputMultiplicador = null;
      const val = Number(this.inputDivisor);
      this.form.factor_conversion = (!isNaN(val) && val > 0)
        ? Number((1 / val).toFixed(6))
        : 1.000000;
    }
  }

  // ─── Modal ────────────────────────────────────────────────────────────────────

  abrirModal(template: TemplateRef<any>): void {
    this.editandoId = null;
    this.resetForm();
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  abrirEditar(presentacion: any, template: TemplateRef<any>): void {
    this.editandoId = presentacion.id;
    const factor = Number(presentacion.factor_conversion);

    this.form = {
      id_unidad_medida: presentacion.id_unidad_medida,
      nombre_comercial: presentacion.nombre_comercial,
      factor_conversion: factor,
      precio_venta:      Number(presentacion.precio_venta),
      codigo_barras:     presentacion.codigo_barras || '',
    };

    // Detectar qué modo usar al abrir el modal de edición
    if (factor >= 1) {
      this.inputMultiplicador = factor;
      this.inputDivisor       = null;
      this.modoEquivalencia   = 'multiplicador';
    } else {
      this.inputMultiplicador = null;
      this.inputDivisor       = Number((1 / factor).toFixed(6));
      this.modoEquivalencia   = 'divisor';
    }

    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  cerrarModal(): void {
    this.modalRef.hide();
  }

  private resetForm(): void {
    this.inputMultiplicador = 1;
    this.inputDivisor       = null;
    this.modoEquivalencia   = 'multiplicador';
    this.form = {
      id_unidad_medida: '',
      nombre_comercial: '',
      factor_conversion: 1.000000,
      precio_venta: 0,
      codigo_barras: '',
    };
  }

  // ─── Guardar ──────────────────────────────────────────────────────────────────

  guardar(): void {
    if (!this.form.id_unidad_medida) {
      this.alertService.warning('Campo requerido', 'Seleccione la unidad de medida fiscal.');
      return;
    }
    if (!this.form.nombre_comercial?.trim()) {
      this.alertService.warning('Campo requerido', 'Ingrese el nombre comercial de la presentación.');
      return;
    }
    if (this.form.factor_conversion <= 0) {
      this.alertService.warning('Factor inválido', 'La equivalencia debe ser mayor a cero.');
      return;
    }

    this.saving = true;

    const payload = {
      id_producto:        this.producto.id,
      id_unidad_medida:   this.form.id_unidad_medida,
      nombre_comercial:   this.form.nombre_comercial.trim(),
      factor_conversion:  this.form.factor_conversion,
      precio_venta:       Number(Number(this.form.precio_venta).toFixed(6)),
      codigo_barras:      this.form.codigo_barras?.trim() || null,
    };

    if (this.editandoId) {
      // ── Modo edición: PUT
      this.apiService.update('producto-presentaciones', this.editandoId, payload).subscribe(
        (respuesta: any) => {
          this.alertService.success('Actualizado', 'La presentación fue actualizada correctamente.');
          const idx = this.presentaciones.findIndex(p => p.id === this.editandoId);
          if (idx !== -1) { this.presentaciones[idx] = respuesta; }
          this.presentaciones = [...this.presentaciones]; // forzar detección de cambios
          this.modalRef.hide();
          this.saving = false;
        },
        (error: any) => { this.alertService.error(error); this.saving = false; }
      );
    } else {
      // ── Modo creación: POST
      this.apiService.store('producto-presentaciones', payload).subscribe(
        (respuesta: any) => {
          this.alertService.success('¡Guardado!', 'La presentación fue creada correctamente.');
          this.presentaciones = [...this.presentaciones, respuesta];
          this.modalRef.hide();
          this.saving = false;
        },
        (error: any) => { this.alertService.error(error); this.saving = false; }
      );
    }
  }

  // ─── Eliminar ─────────────────────────────────────────────────────────────────

  eliminar(presentacion: any): void {
    if (!confirm(`¿Eliminar la presentación "${presentacion.nombre_comercial}"?`)) {
      return;
    }
    this.apiService.delete('producto-presentaciones/', presentacion.id).subscribe(
      () => {
        this.presentaciones = this.presentaciones.filter(p => p.id !== presentacion.id);
        this.alertService.success('Eliminado', 'La presentación fue eliminada.');
      },
      (error: any) => { this.alertService.error(error); }
    );
  }
}
