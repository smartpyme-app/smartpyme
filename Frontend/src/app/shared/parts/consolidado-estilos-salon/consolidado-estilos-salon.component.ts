import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-consolidado-estilos-salon',
  templateUrl: './consolidado-estilos-salon.component.html',
})
export class ConsolidadoEstilosSalonComponent implements OnInit {
  public disponible = false;
  public downloading = false;
  public fechaInicio = '';
  public fechaFin = '';
  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit(): void {
    this.apiService.getAll('reporte/estilos-salon/consolidado').subscribe(
      (res) => {
        this.disponible = !!res?.disponible;
        this.fechaInicio = res?.fecha_inicio || '';
        this.fechaFin = res?.fecha_fin || '';
      },
      () => {
        this.disponible = false;
      }
    );
  }

  public abrir(template: TemplateRef<any>): void {
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { backdrop: 'static' });
  }

  public cerrar(): void {
    this.modalRef?.hide();
    this.alertService.modal = false;
  }

  public descargar(): void {
    if (!this.fechaInicio || !this.fechaFin || this.fechaInicio > this.fechaFin) {
      this.alertService.error('Seleccione un rango de fechas válido');
      return;
    }

    this.downloading = true;

    this.apiService
      .export(
        'reporte/estilos-salon/consolidado/excel',
        { fecha_inicio: this.fechaInicio, fecha_fin: this.fechaFin },
        300000
      )
      .subscribe(
        (data: Blob) => {
          this.apiService.downloadFile(
            data,
            `ventas-por-categoria-sucursal-${this.fechaInicio}-${this.fechaFin}.xlsx`
          );
          this.downloading = false;
          this.cerrar();
        },
        (error) => {
          this.alertService.error(error);
          this.downloading = false;
        }
      );
  }
}
