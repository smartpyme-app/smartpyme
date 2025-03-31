import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaConstants } from '../../../constants/planilla.constants';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-boleta-pago',
  templateUrl: './boleta-pago.component.html',
})
export class BoletaPagoComponent implements OnInit {
  planillaDetalle: any;
  
  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.route.params.subscribe(params => {
      if (params['id']) {
        this.cargarDetallePlanilla(params['id']);
      }
    });
  }

  cargarDetallePlanilla(id: number) {
    this.apiService.read('planillas/detalles/', id).subscribe({
      next: (detalle) => {
        this.planillaDetalle = detalle;
      },
      error: (error) => {
        this.alertService.error(error);
      }
    });
  }

  generarPDF() {
    if (!this.planillaDetalle?.id) {
      this.alertService.error('No se ha cargado el detalle de la planilla');
      return;
    }

    this.apiService.download(`planillas/detalles/${this.planillaDetalle.id}/pdf`).subscribe({
      next: (response) => {
        const blob = new Blob([response], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        window.open(url);
      },
      error: (error) => {
        this.alertService.error('Error al generar el PDF');
      }
    });
  }

  enviarPorEmail() {
    if (!this.planillaDetalle?.id) {
      this.alertService.error('No se ha cargado el detalle de la planilla');
      return;
    }

    this.apiService.store(`planillas/detalles/${this.planillaDetalle.id}/enviar-email`, {}).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', 'Boleta enviada por correo exitosamente');
      },
      error: (error) => {
        this.alertService.error('Error al enviar el correo');
      }
    });
  }
}