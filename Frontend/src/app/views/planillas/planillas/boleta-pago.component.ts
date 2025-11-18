import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PlanillaConstants } from '../../../constants/planilla.constants';
import { ActivatedRoute } from '@angular/router';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-boleta-pago',
    templateUrl: './boleta-pago.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class BoletaPagoComponent implements OnInit {
  planillaDetalle: any;
  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);
  
  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.route.params.pipe(this.untilDestroyed()).subscribe(params => {
      if (params['id']) {
        this.cargarDetallePlanilla(params['id']);
      }
    });
  }

  cargarDetallePlanilla(id: number) {
    this.apiService.read('planillas/detalles/', id).pipe(this.untilDestroyed()).subscribe({
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

    this.apiService.download(`planillas/detalles/${this.planillaDetalle.id}/pdf`).pipe(this.untilDestroyed()).subscribe({
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

    this.apiService.store(`planillas/detalles/${this.planillaDetalle.id}/enviar-email`, {}).pipe(this.untilDestroyed()).subscribe({
      next: (response) => {
        this.alertService.success('Éxito', 'Boleta enviada por correo exitosamente');
      },
      error: (error) => {
        this.alertService.error('Error al enviar el correo');
      }
    });
  }
}