import { Component, EventEmitter, Output, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { CostaRicaFacturacionElectronicaService } from '@services/facturacion-electronica/costa-rica-facturacion-electronica.service';
import {
  FE_CR_TIPOS_DOCUMENTO_EXONERACION,
  FeCrExoneracionDetalle,
  aplicarExoneracionGuardadaEnDetalle,
  aplicarRespuestaExoneracionHacienda,
  baseFeCrExoneracionDetalle,
  initFeCrExoneracionDetalle,
  validarExoneracionForm,
} from './fe-cr-exoneracion-detalle.util';

@Component({
  selector: 'app-fe-cr-exoneracion-detalle-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './fe-cr-exoneracion-detalle-modal.component.html',
})
export class FeCrExoneracionDetalleModalComponent {
  @ViewChild('tpl') tpl!: TemplateRef<any>;
  @Output() saved = new EventEmitter<any>();

  readonly tiposDocumentoEx = FE_CR_TIPOS_DOCUMENTO_EXONERACION;

  detalleEdit: any = null;
  detalleTeniaExoneracion = false;
  form: FeCrExoneracionDetalle = baseFeCrExoneracionDetalle();
  loadingHacienda = false;
  private modalRef?: BsModalRef;

  constructor(
    private modalService: BsModalService,
    private alertService: AlertService,
    private feCrService: CostaRicaFacturacionElectronicaService
  ) {}

  abrir(detalle: any): void {
    this.detalleEdit = detalle;
    initFeCrExoneracionDetalle(detalle);
    this.form = { ...baseFeCrExoneracionDetalle(), ...detalle.fe_cr_exoneracion, aplica: true };
    this.detalleTeniaExoneracion = !!(detalle.fe_cr_exoneracion?.aplica);
    if (!this.form.fecha_emision) {
      this.form.fecha_emision = new Date().toISOString().slice(0, 10);
    }
    this.modalRef = this.modalService.show(this.tpl, { class: 'modal-lg', backdrop: 'static' });
  }

  cerrar(): void {
    this.modalRef?.hide();
    this.detalleEdit = null;
    this.detalleTeniaExoneracion = false;
    this.form = baseFeCrExoneracionDetalle();
  }

  consultarHacienda(): void {
    const num = (this.form.numero_documento || '').trim();
    if (!/^AL-\d{8}-\d{2}$/i.test(num)) {
      this.alertService.warning(null, 'Indique un número de autorización con formato AL-XXXXXXXX-XX.');
      return;
    }
    this.loadingHacienda = true;
    this.feCrService.consultarExoneracion(num).subscribe({
      next: (data: any) => {
        this.loadingHacienda = false;
        if (!data || (typeof data === 'object' && Object.keys(data).length === 0)) {
          this.alertService.warning(null, 'Hacienda no devolvió datos para esa autorización.');
          return;
        }
        this.form = aplicarRespuestaExoneracionHacienda(this.form, data as Record<string, unknown>);
      },
      error: (err) => {
        this.loadingHacienda = false;
        this.alertService.error(err?.error?.error || err?.message || 'No se pudo consultar la exoneración.');
      },
    });
  }

  guardar(): void {
    if (!this.detalleEdit) {
      return;
    }
    this.form.aplica = true;
    const faltan = validarExoneracionForm(this.form);
    if (faltan.length) {
      this.alertService.warning(null, 'Complete: ' + faltan.join(', ') + '.');
      return;
    }
    aplicarExoneracionGuardadaEnDetalle(this.detalleEdit, this.form);
    this.saved.emit(this.detalleEdit);
    this.cerrar();
  }

  quitarExoneracion(): void {
    if (!this.detalleEdit) {
      return;
    }
    const vacio = baseFeCrExoneracionDetalle();
    vacio.aplica = false;
    aplicarExoneracionGuardadaEnDetalle(this.detalleEdit, vacio);
    this.saved.emit(this.detalleEdit);
    this.cerrar();
  }
}
