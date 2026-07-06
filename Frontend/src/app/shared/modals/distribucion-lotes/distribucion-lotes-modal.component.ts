import { Component, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import {
  asignacionLotesExcedeStock,
  autoDistribuirCantidadesLotes,
  factorConversionDetalle,
  formatCantidadLote,
  stockBaseAUnidadesDetalle,
  textoResumenLotesDetalle,
  totalAsignadoUnidadesLotes,
} from '@utils/lotes-venta.util';

@Component({
  selector: 'app-distribucion-lotes-modal',
  templateUrl: './distribucion-lotes-modal.component.html',
})
export class DistribucionLotesModalComponent {
  @Input() etiquetaCantidad = 'Total';
  @Output() confirmado = new EventEmitter<any>();

  @ViewChild('modalTemplate') modalTemplate!: TemplateRef<any>;

  public detalle: any = null;
  public idBodega: number | null = null;
  public lotes: any[] = [];
  public loading = false;
  public cantidadObjetivoModal = 1;
  public formatCantidadLote = formatCantidadLote;

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
  ) {}

  abrir(detalle: any, idBodega: number): void {
    this.detalle = detalle;
    this.idBodega = idBodega;
    const previos = detalle.lotes_asignados || [];
    const factor = factorConversionDetalle(detalle);
    this.cantidadObjetivoModal = previos.length
      ? previos.reduce((s: number, p: any) => s + ((parseFloat(String(p.cantidad)) || 0) / (factor || 1)), 0)
      : parseFloat(String(detalle.cantidad)) || 1;
    this.cargarLotes();
    this.modalRef = this.modalService.show(this.modalTemplate, { class: 'modal-lg', backdrop: 'static' });
  }

  cargarLotes(): void {
    const idProducto = this.detalle?.id_producto || this.detalle?.producto_id;
    if (!idProducto || !this.idBodega) {
      return;
    }

    this.loading = true;
    this.apiService.getAll('lotes/disponibles', {
      id_producto: idProducto,
      id_bodega: this.idBodega,
    }).subscribe(lotes => {
      const previos = this.detalle.lotes_asignados || [];
      const factor = factorConversionDetalle(this.detalle);
      this.lotes = (lotes || []).map((lote: any) => {
        const previo = previos.find((p: any) => p.lote_id == lote.id);
        const cantidadBase = previo ? parseFloat(String(previo.cantidad)) || 0 : 0;
        return {
          ...lote,
          cantidad_asignada: factor > 0 ? cantidadBase / factor : cantidadBase,
          stock_unidades: stockBaseAUnidadesDetalle(lote.stock, this.detalle),
        };
      });
      this.loading = false;
    }, error => {
      this.alertService.error(error);
      this.loading = false;
    });
  }

  autoDistribuir(): void {
    const objetivo = parseFloat(String(this.cantidadObjetivoModal)) || 0;
    if (objetivo <= 0) {
      this.alertService.error('Indique una cantidad a distribuir mayor a cero.');
      return;
    }
    autoDistribuirCantidadesLotes(
      this.lotes,
      objetivo * factorConversionDetalle(this.detalle),
      this.detalle
    );
  }

  totalAsignadoUnidades(): number {
    return totalAsignadoUnidadesLotes(this.lotes);
  }

  distribucionValida(): boolean {
    return this.totalAsignadoUnidades() > 0 && !asignacionLotesExcedeStock(this.lotes);
  }

  confirmar(): void {
    const factor = factorConversionDetalle(this.detalle);
    const totalUnidades = this.totalAsignadoUnidades();

    if (totalUnidades <= 0) {
      this.alertService.error('Indique al menos un lote con cantidad.');
      return;
    }

    if (asignacionLotesExcedeStock(this.lotes)) {
      this.alertService.error('Alguna cantidad supera el stock disponible del lote.');
      return;
    }

    const asignaciones = this.lotes
      .filter((lote: any) => (parseFloat(String(lote.cantidad_asignada)) || 0) > 0)
      .map((lote: any) => ({
        lote_id: lote.id,
        numero_lote: lote.numero_lote,
        cantidad: (parseFloat(String(lote.cantidad_asignada)) || 0) * factor,
      }));

    this.detalle.lotes_asignados = asignaciones;
    if (asignaciones.length === 1) {
      this.detalle.lote_id = asignaciones[0].lote_id;
      this.detalle.lote = this.lotes.find((l: any) => l.id == asignaciones[0].lote_id);
    } else {
      this.detalle.lote_id = null;
      this.detalle.lote = null;
    }
    this.detalle.cantidad = totalUnidades;

    this.modalRef.hide();
    this.confirmado.emit(this.detalle);
  }

  textoResumen(detalle: any): string {
    return textoResumenLotesDetalle(detalle);
  }

  nombreProducto(): string {
    return this.detalle?.nombre_producto
      || this.detalle?.descripcion
      || this.detalle?.nombre
      || 'Producto';
  }
}
