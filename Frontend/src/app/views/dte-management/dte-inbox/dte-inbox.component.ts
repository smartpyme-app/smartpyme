import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { DteDocumentService, DteDocument, DteDocumentsResponse } from '@services/dte-management/dte-document.service';

@Component({
  selector: 'app-dte-inbox',
  templateUrl: './dte-inbox.component.html'
})
export class DteInboxComponent implements OnInit {
  documents: DteDocumentsResponse | null = null;
  loading = false;
  filtros: any = {};

  constructor(
    private dteService: DteDocumentService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.route.queryParams.subscribe((params) => {
      this.filtros = {
        validation_status: params['validation_status'] || '',
        processing_status: params['processing_status'] || '',
        buscador: params['buscador'] || '',
        inicio: params['inicio'] || '',
        fin: params['fin'] || '',
        page: +(params['page'] || 1),
        per_page: +(params['per_page'] || 15)
      };
      this.loadDocuments();
    });
  }

  loadDocuments(): void {
    this.loading = true;
    const params: any = { page: this.filtros.page, per_page: this.filtros.per_page };
    if (this.filtros.validation_status) params.validation_status = this.filtros.validation_status;
    if (this.filtros.processing_status) params.processing_status = this.filtros.processing_status;
    if (this.filtros.buscador) params.buscador = this.filtros.buscador;
    if (this.filtros.inicio) params.inicio = this.filtros.inicio;
    if (this.filtros.fin) params.fin = this.filtros.fin;

    this.dteService.list(params).subscribe({
      next: (data) => {
        this.documents = data;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      }
    });
  }

  setPagination(event: any): void {
    this.filtros.page = event.page;
    this.router.navigate([], { relativeTo: this.route, queryParams: this.filtros, queryParamsHandling: 'merge' });
  }

  filtrar(): void {
    this.filtros.page = 1;
    this.router.navigate([], { relativeTo: this.route, queryParams: this.filtros, queryParamsHandling: 'merge' });
  }

  validationBadgeClass(status: string): string {
    return status === 'valid' ? 'bg-success' : 'bg-danger';
  }

  processingBadgeClass(status: string): string {
    switch (status) {
      case 'processed': return 'bg-success';
      case 'pending': return 'bg-primary';
      case 'pendiente_clasificacion': return 'bg-warning';
      case 'failed': return 'bg-danger';
      default: return 'bg-secondary';
    }
  }

  dteTypeLabel(type: string): string {
    const map: Record<string, string> = {
      '01': 'FC', '03': 'CCF', '04': 'NR', '05': 'NC', '06': 'ND', '11': 'FEX', '14': 'SE'
    };
    return map[type] || type;
  }

  validationStatusLabel(status: string): string {
    const map: Record<string, string> = {
      valid: 'Válido',
      invalid: 'Inválido'
    };
    return map[status] || status;
  }

  processingStatusLabel(status: string): string {
    const map: Record<string, string> = {
      pending: 'Pendiente',
      pendiente_clasificacion: 'Pendiente clasificación',
      processed: 'Procesado',
      failed: 'Fallido'
    };
    return map[status] || status;
  }
}
