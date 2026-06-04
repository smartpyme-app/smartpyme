import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, ActivatedRoute, Router } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { DteDocumentService, DteDocument } from '@services/dte-management/dte-document.service';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';

@Component({
  selector: 'app-dte-detail',
  templateUrl: './dte-detail.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule],
})
export class DteDetailComponent implements OnInit {
  document: DteDocument | null = null;
  loading = false;
  downloading = false;
  procesando = false;
  destinoSeleccionado: 'compra' | 'gasto' = 'compra';

  modalRef?: BsModalRef;
  jsonPreview = '';
  pdfPreviewUrl: SafeResourceUrl | null = null;
  private pdfObjectUrl: string | null = null;

  constructor(
    private dteService: DteDocumentService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private modalService: BsModalService,
    private sanitizer: DomSanitizer
  ) {}

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.loadDocument(+id);
    }
  }

  loadDocument(id: number): void {
    this.loading = true;
    this.dteService.get(id).subscribe({
      next: (doc) => {
        this.document = doc;
        this.destinoSeleccionado = (doc.destino || 'compra') as 'compra' | 'gasto';
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
        this.router.navigate(['/dte-management/dtes']);
      }
    });
  }

  goBack(): void {
    this.router.navigate(['/dte-management/dtes']);
  }

  downloadJson(): void {
    if (!this.document) return;
    this.downloading = true;
    this.dteService.downloadJson(this.document.id).subscribe({
      next: (blob) => {
        this.triggerDownload(blob, `${this.document!.dte_uuid}.json`);
        this.downloading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.downloading = false;
      }
    });
  }

  downloadPdf(): void {
    if (!this.document) return;
    this.downloading = true;
    this.dteService.downloadPdf(this.document.id).subscribe({
      next: (blob) => {
        this.triggerDownload(blob, `${this.document!.dte_uuid}.pdf`);
        this.downloading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.downloading = false;
      }
    });
  }

  openVerJson(template: TemplateRef<any>): void {
    if (!this.document) return;
    this.dteService.downloadJson(this.document.id).subscribe({
      next: async (blob) => {
        const text = await blob.text();
        try {
          this.jsonPreview = JSON.stringify(JSON.parse(text), null, 2);
        } catch {
          this.jsonPreview = text;
        }
        this.modalRef = this.modalService.show(template, { class: 'modal-xl' });
      },
      error: (err) => this.alertService.error(err)
    });
  }

  openVerPdf(template: TemplateRef<any>): void {
    if (!this.document) return;
    this.dteService.downloadPdf(this.document.id).subscribe({
      next: (blob) => {
        if (this.pdfObjectUrl) {
          URL.revokeObjectURL(this.pdfObjectUrl);
        }
        this.pdfObjectUrl = URL.createObjectURL(blob);
        this.pdfPreviewUrl = this.sanitizer.bypassSecurityTrustResourceUrl(this.pdfObjectUrl);
        this.modalRef = this.modalService.show(template, { class: 'modal-xl' });
      },
      error: (err) => this.alertService.error(err)
    });
  }

  closeModal(): void {
    this.modalRef?.hide();
    if (this.pdfObjectUrl) {
      URL.revokeObjectURL(this.pdfObjectUrl);
      this.pdfObjectUrl = null;
      this.pdfPreviewUrl = null;
    }
  }

  private triggerDownload(blob: Blob, filename: string): void {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
  }

  dteTypeLabel(type: string): string {
    const map: Record<string, string> = {
      '01': 'Factura Consumidor Final', '03': 'Crédito Fiscal', '04': 'Nota de Remisión',
      '05': 'Nota de Crédito', '06': 'Nota de Débito', '11': 'Factura Exportación', '14': 'Sujeto Excluido'
    };
    return map[type] || type;
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

  cambiarDestino(): void {
    if (!this.document) return;
    this.dteService.updateDestino(this.document.id, this.destinoSeleccionado).subscribe({
      next: (res) => {
        this.document = res.document;
        this.alertService.success('Éxito', 'Destino actualizado');
      },
      error: (err) => this.alertService.error(err)
    });
  }

  procesar(): void {
    if (!this.document) return;
    this.procesando = true;
    const destActual = this.document.destino || 'compra';
    const needUpdate = destActual !== this.destinoSeleccionado;
    const doProcesar = () => {
      this.dteService.procesar(this.document!.id).subscribe({
        next: (res) => {
          this.document = res.document || this.document!;
          this.alertService.success('Éxito', res.message || 'DTE procesado correctamente');
          this.procesando = false;
        },
        error: (err) => {
          this.alertService.error(err?.error?.error || err?.error?.reason || 'Error al procesar');
          this.procesando = false;
        }
      });
    };
    if (needUpdate) {
      this.dteService.updateDestino(this.document.id, this.destinoSeleccionado).subscribe({
        next: (res) => { this.document = res.document!; doProcesar(); },
        error: (err) => { this.alertService.error(err); this.procesando = false; }
      });
    } else {
      doProcesar();
    }
  }
}
