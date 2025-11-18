import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-ver-boletas',
    templateUrl: './ver-boletas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    styles: [
        `
      .pdf-container {
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #f8f9fa;
      }

      object {
        display: block;
        margin: 0 auto;
      }
    `,
    ],
    
})
export class VerBoletasComponent implements OnInit {
  public planillaId: number;
  public planilla: any = {};
  public pdfUrl: SafeResourceUrl | null = null;
  public loading: boolean = false;
  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private route: ActivatedRoute,
    private apiService: ApiService,
    private alertService: AlertService,
    private sanitizer: DomSanitizer
  ) {
    this.planillaId = 0;
  }

  ngOnInit() {
    this.route.params.pipe(this.untilDestroyed()).subscribe((params) => {
      if (params['id']) {
        this.planillaId = params['id'];
        this.cargarPlanilla();
        this.cargarBoletas();
      }
    });
  }

  cargarPlanilla() {
    // Crear objeto de parámetros
    const params = {
        id: this.planillaId,
    };

    this.apiService.getAll('planillas/detalles', params).pipe(this.untilDestroyed()).subscribe({
      next: (response) => {
        this.planilla = response;
      },
      error: (error) => {
        this.alertService.error(error);
      }
    });
}

  cargarBoletas() {
    this.loading = true;
    this.apiService.generatePayrollSlips(this.planillaId).pipe(this.untilDestroyed()).subscribe({
      next: (response: Blob) => {
        const url = URL.createObjectURL(response);
        this.pdfUrl = this.sanitizer.bypassSecurityTrustResourceUrl(url);
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error('Error al cargar las boletas');
        this.loading = false;
      },
    });
  }

  descargarPDF() {
    if (this.pdfUrl) {
      window.open(this.pdfUrl.toString(), '_blank');
    }
  }
}
