import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { DteDocumentService, DteDocument } from '@services/dte-management/dte-document.service';

@Component({
  selector: 'app-dte-detail',
  templateUrl: './dte-detail.component.html'
})
export class DteDetailComponent implements OnInit {
  document: DteDocument | null = null;
  loading = false;
  downloading = false;
  procesando = false;
  destinoSeleccionado: 'compra' | 'gasto' = 'compra';

  constructor(
    private dteService: DteDocumentService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
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

  downloadJson(): void {
    if (!this.document) return;
    this.downloading = true;
    this.dteService.downloadJson(this.document.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.document!.dte_uuid}.json`;
        a.click();
        window.URL.revokeObjectURL(url);
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
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.document!.dte_uuid}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.downloading = false;
      }
    });
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
      failed: 'Fallido'
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
