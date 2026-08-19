import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

@Component({
  selector: 'app-consolidado-estilos-salon',
  templateUrl: './consolidado-estilos-salon.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, TooltipModule, NotificacionesContainerComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConsolidadoEstilosSalonComponent extends BaseModalComponent implements OnInit {
  public disponible = false;
  public downloading = false;
  public fechaInicio = '';
  public fechaFin = '';

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private cdr: ChangeDetectorRef
  ) {
    super(modalManager, alertService);
  }

  ngOnInit(): void {
    this.apiService
      .getAll('reporte/estilos-salon/consolidado')
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (res) => {
          this.disponible = !!res?.disponible;
          this.fechaInicio = res?.fecha_inicio || '';
          this.fechaFin = res?.fecha_fin || '';
          this.cdr.markForCheck();
        },
        error: () => {
          this.disponible = false;
          this.cdr.markForCheck();
        },
      });
  }

  public abrir(template: TemplateRef<any>): void {
    this.openModal(template, { backdrop: 'static' });
  }

  public descargar(): void {
    if (!this.fechaInicio || !this.fechaFin || this.fechaInicio > this.fechaFin) {
      this.alertService.error('Seleccione un rango de fechas válido');
      return;
    }

    this.downloading = true;
    this.cdr.markForCheck();

    this.apiService
      .export(
        'reporte/estilos-salon/consolidado/excel',
        { fecha_inicio: this.fechaInicio, fecha_fin: this.fechaFin },
        300000
      )
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (data: Blob) => {
          this.apiService.downloadFile(
            data,
            `ventas-por-categoria-sucursal-${this.fechaInicio}-${this.fechaFin}.xlsx`
          );
          this.downloading = false;
          this.closeModal();
          this.cdr.markForCheck();
        },
        error: (error) => {
          this.alertService.error(error);
          this.downloading = false;
          this.cdr.markForCheck();
        },
      });
  }
}
