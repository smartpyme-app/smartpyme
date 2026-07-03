import { Component, OnInit, TemplateRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router, ActivatedRoute } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { AlertService } from '@services/alert.service';
import { DteDocumentService, DteDocument, DteDocumentsResponse } from '@services/dte-management/dte-document.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { CurrencyFormatService } from '@services/currency-format.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { TranslatePipe } from '@ngx-translate/core';

@Component({
  selector: 'app-dte-inbox',
  templateUrl: './dte-inbox.component.html',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    TooltipModule,
    PopoverModule,
    PaginationComponent,
    TruncatePipe,
    TranslatePipe,
  ],
})
export class DteInboxComponent implements OnInit {
  private readonly countryI18n = inject(CountryI18nService);
  private readonly currencyFormat = inject(CurrencyFormatService);

  documents: DteDocumentsResponse | null = null;
  loading = false;
  filtros: any = {};
  modalRef?: BsModalRef;
  modalProcesarRef?: BsModalRef;
  documentoProcesar: DteDocument | null = null;
  destinoSeleccionado: 'compra' | 'gasto' = 'compra';
  procesando = false;

  constructor(
    private dteService: DteDocumentService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute,
    private modalService: BsModalService
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
    this.modalRef?.hide();
    this.router.navigate([], { relativeTo: this.route, queryParams: this.filtros, queryParamsHandling: 'merge' });
  }

  openFilter(template: TemplateRef<any>): void {
    this.modalRef = this.modalService.show(template, { class: 'modal-md' });
  }

  openProcesar(template: TemplateRef<any>, doc: DteDocument): void {
    if (doc.validation_status !== 'valid' || doc.processing_status === 'processed') {
      return;
    }
    this.documentoProcesar = doc;
    this.destinoSeleccionado = (doc.destino || (doc.pais === 'CR' ? 'gasto' : 'compra')) as 'compra' | 'gasto';
    this.procesando = false;
    this.modalProcesarRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  confirmarProcesar(): void {
    if (!this.documentoProcesar) return;
    this.procesando = true;
    const doc = this.documentoProcesar;

    this.dteService.procesar(doc.id, {
      destino: this.destinoSeleccionado,
    }).subscribe({
      next: (res) => {
        this.alertService.success('Éxito', res.message || this.countryI18n.fe('processedDefault'));
        this.modalProcesarRef?.hide();
        this.documentoProcesar = null;
        this.procesando = false;
        this.loadDocuments();
      },
      error: (err) => {
        const msg = err?.error?.error || err?.error?.reason || 'Error al procesar';
        this.alertService.error(typeof msg === 'string' ? { error: { message: msg } } : err);
        this.procesando = false;
      }
    });
  }

  puedeProcesar(doc: DteDocument): boolean {
    return doc.validation_status === 'valid'
      && doc.processing_status !== 'processed'
      && doc.processing_status !== 'anulado';
  }

  puedeAnular(doc: DteDocument): boolean {
    return doc.processing_status !== 'processed' && doc.processing_status !== 'anulado';
  }

  anularDocumento(doc: DteDocument): void {
    if (!this.puedeAnular(doc)) {
      return;
    }
    if (!confirm(this.countryI18n.fe('annulInboxConfirm', {
      label: this.countryI18n.fe('label'),
      number: doc.dte_number || doc.dte_uuid,
    }))) {
      return;
    }
    this.dteService.anular(doc.id).subscribe({
      next: (res) => {
        this.alertService.success('Éxito', res.message || this.countryI18n.fe('annulDefault'));
        this.loadDocuments();
      },
      error: (err) => this.alertService.error(err)
    });
  }

  validationBadgeClass(status: string): string {
    return status === 'valid' ? 'bg-success' : 'bg-danger';
  }

  processingBadgeClass(status: string): string {
    switch (status) {
      case 'processed': return 'bg-success';
      case 'pending': return 'bg-primary';
      case 'pendiente_clasificacion': return 'bg-warning';
      case 'anulado': return 'bg-secondary';
      case 'failed': return 'bg-danger';
      default: return 'bg-secondary';
    }
  }

  dteTypeLabel(doc: DteDocument): string {
    const type = doc.dte_type;
    if (doc.pais === 'CR') {
      const mapCr: Record<string, string> = {
        '01': 'FE', '02': 'ND', '03': 'NC', '04': 'TE', '08': 'FEC', '09': 'FEX',
      };
      return mapCr[type] || type;
    }
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
      failed: 'Fallido',
      anulado: 'Anulado'
    };
    return map[status] || status;
  }

  formatMoney(value: number | null | undefined): string {
    return this.currencyFormat.format(value);
  }
}
