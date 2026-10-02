import {
  Component,
  TemplateRef,
  ViewChild,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import {
  RecurrenciaVentaConfig,
  aplicarRecurrenciaEnVenta,
  diaRecurrenciaDesdeVenta,
  payloadRecurrenciaApi,
} from '@utils/venta-recurrencia.util';

@Component({
  selector: 'app-venta-recurrencia-config',
  templateUrl: './venta-recurrencia-config.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VentaRecurrenciaConfigComponent extends BaseComponent {
  @ViewChild('modalTpl', { static: true }) modalTpl!: TemplateRef<unknown>;

  public frecuenciaRecurrencia: 'mensual' | 'anual' = 'mensual';
  public diaGeneracionRecurrencia = 1;
  public fechaVentaReferencia = '';
  public recurrenciaPausada = false;
  public saving = false;

  private modalRef?: BsModalRef;
  private ventaObjetivo: any = null;
  private resolver?: (value: RecurrenciaVentaConfig | null) => void;

  constructor(
    private modalService: BsModalService,
    private apiService: ApiService,
    private alertService: AlertService,
    private cdr: ChangeDetectorRef,
  ) {
    super();
  }

  /** Si la venta ya tiene id, persiste en API; si no, devuelve la config para aplicar tras facturar. */
  open(venta: any): Promise<RecurrenciaVentaConfig | null> {
    this.ventaObjetivo = venta;
    this.frecuenciaRecurrencia = venta?.frecuencia_recurrencia === 'anual' ? 'anual' : 'mensual';
    this.diaGeneracionRecurrencia = diaRecurrenciaDesdeVenta(venta);
    this.fechaVentaReferencia = venta?.fecha ? String(venta.fecha).slice(0, 10) : '';
    this.recurrenciaPausada = !!venta?.recurrencia_pausada;
    this.saving = false;
    this.modalRef = this.modalService.show(this.modalTpl, { class: 'modal-md', backdrop: 'static' });
    this.cdr.markForCheck();

    return new Promise((resolve) => {
      this.resolver = resolve;
    });
  }

  guardar(): void {
    const dia = Math.min(31, Math.max(1, Math.trunc(Number(this.diaGeneracionRecurrencia) || 1)));
    const config = payloadRecurrenciaApi(this.frecuenciaRecurrencia, this.recurrenciaPausada, dia);
    if (!this.ventaObjetivo?.id) {
      this.cerrar(config);
      return;
    }

    this.saving = true;
    this.cdr.markForCheck();
    this.apiService
      .store('venta/' + this.ventaObjetivo.id + '/recurrencia', config)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (venta) => {
          aplicarRecurrenciaEnVenta(this.ventaObjetivo, config);
          this.ventaObjetivo.frecuencia_recurrencia = venta.frecuencia_recurrencia;
          this.ventaObjetivo.recurrencia_pausada = venta.recurrencia_pausada;
          this.ventaObjetivo.dia_generacion_recurrencia = venta.dia_generacion_recurrencia;
          this.ventaObjetivo.recurrente = venta.recurrente;
          this.saving = false;
          this.alertService.success(
            'Listo',
            config.pausada
              ? 'Quedó en pausa.'
              : this.frecuenciaRecurrencia === 'anual'
                ? `Se generará el día ${dia} de cada año (mismo mes que la plantilla).`
                : `Se generará el día ${dia} de cada mes.`,
          );
          this.cerrar(config);
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.saving = false;
          this.cdr.markForCheck();
        },
      });
  }

  cancelar(): void {
    this.cerrar(null);
  }

  private cerrar(config: RecurrenciaVentaConfig | null): void {
    this.modalRef?.hide();
    this.resolver?.(config);
    this.resolver = undefined;
  }
}
