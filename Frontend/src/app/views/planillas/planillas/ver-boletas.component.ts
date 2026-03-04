import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';

@Component({
  selector: 'app-ver-boletas',
  templateUrl: './ver-boletas.component.html',
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

  constructor(
    private route: ActivatedRoute,
    private apiService: ApiService,
    private alertService: AlertService,
    private sanitizer: DomSanitizer
  ) {
    this.planillaId = 0;
  }

  ngOnInit() {
    this.route.params.subscribe((params) => {
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

    this.apiService.getAll('planillas/detalles', params).subscribe({
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
    this.apiService.generatePayrollSlips(this.planillaId).subscribe({
      next: (response: Blob) => {
        const url = URL.createObjectURL(response);
        this.pdfUrl = this.sanitizer.bypassSecurityTrustResourceUrl(url);
        this.loading = false;
      },
      error: (error: any) => {
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
