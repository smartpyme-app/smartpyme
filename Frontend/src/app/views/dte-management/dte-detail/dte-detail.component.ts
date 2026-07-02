import { Component, OnInit, TemplateRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, ActivatedRoute, Router } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { DteDocumentService, DteDocument, DteLineItem, DteProcesarPayload } from '@services/dte-management/dte-document.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { CurrencyFormatService } from '@services/currency-format.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { TranslatePipe } from '@ngx-translate/core';
import { CrearProyectoComponent } from '@shared/modals/crear-proyecto/crear-proyecto.component';

@Component({
  selector: 'app-dte-detail',
  templateUrl: './dte-detail.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule, NgSelectModule, TranslatePipe, CrearProyectoComponent],
})
export class DteDetailComponent implements OnInit {
  private readonly countryI18n = inject(CountryI18nService);
  private readonly currencyFormat = inject(CurrencyFormatService);
  readonly apiService = inject(ApiService);

  document: DteDocument | null = null;
  lineItems: DteLineItem[] = [];
  loading = false;
  downloading = false;
  procesando = false;
  guardando = false;
  destinoSeleccionado: 'compra' | 'gasto' = 'compra';
  idProyecto: number | null = null;
  idCategoria: number | null = null;
  tipoGasto = '';
  tipoCostoGasto = '';
  contabilidadHabilitada = false;
  proyectos: any[] = [];
  categorias: any[] = [];

  modalRef?: BsModalRef;
  jsonPreview = '';
  pdfPreviewUrl: SafeResourceUrl | null = null;
  private pdfObjectUrl: string | null = null;
  private metadataReady = false;
  private metadataSaveTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(
    private dteService: DteDocumentService,
    private alertService: AlertService,
    private funcionalidadesService: FuncionalidadesService,
    private route: ActivatedRoute,
    private router: Router,
    private modalService: BsModalService,
    private sanitizer: DomSanitizer
  ) {}

  ngOnInit(): void {
    this.verificarAccesoContabilidad();
    this.cargarProyectos();
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.loadDocument(+id);
    }
  }

  get esCompra(): boolean {
    return this.destinoSeleccionado === 'compra';
  }

  get esGasto(): boolean {
    return this.destinoSeleccionado === 'gasto';
  }

  get puedeEditarClasificacion(): boolean {
    if (!this.document) return false;
    return this.document.validation_status === 'valid'
      && this.document.processing_status !== 'processed'
      && this.document.processing_status !== 'anulado';
  }

  verificarAccesoContabilidad(): void {
    this.funcionalidadesService.verificarAcceso('contabilidad').subscribe({
      next: (acceso) => {
        this.contabilidadHabilitada = acceso;
        this.cargarCategorias();
      },
      error: () => {
        this.contabilidadHabilitada = false;
        this.cargarCategorias();
      }
    });
  }

  cargarProyectos(): void {
    if (!this.apiService.auth_user()?.empresa?.modulo_proyectos) {
      return;
    }
    this.apiService.getAll('proyectos/list').subscribe({
      next: (data) => { this.proyectos = data; },
      error: (err) => this.alertService.error(err)
    });
  }

  cargarCategorias(): void {
    if (!this.apiService.mostrarMenuConfigGastos(this.contabilidadHabilitada)) {
      this.categorias = [];
      return;
    }
    this.apiService.getAll('gastos/categorias/list').subscribe({
      next: (data) => { this.categorias = data; },
      error: (err) => this.alertService.error(err)
    });
  }

  loadDocument(id: number): void {
    this.loading = true;
    this.metadataReady = false;
    this.dteService.get(id).subscribe({
      next: (doc) => {
        this.document = doc;
        this.lineItems = doc.line_items || [];
        this.destinoSeleccionado = (doc.destino || (doc.pais === 'CR' ? 'gasto' : 'compra')) as 'compra' | 'gasto';
        this.idProyecto = doc.id_proyecto ?? null;
        this.idCategoria = doc.id_categoria ?? null;
        this.tipoGasto = doc.tipo_gasto || this.inferirTipoGasto(this.lineItems) || '';
        this.tipoCostoGasto = doc.tipo_costo_gasto || '';
        this.loading = false;
        setTimeout(() => { this.metadataReady = true; });
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
        this.router.navigate(['/dte-management/dtes']);
      }
    });
  }

  private inferirTipoGasto(items: DteLineItem[]): string | null {
    if (!items.length) return null;
    const keywords: Record<string, string[]> = {
      Alquiler: ['alquiler', 'renta', 'arrendamiento'],
      Combustible: ['combustible', 'gasolina', 'diesel'],
      Servicios: ['servicio', 'electricidad', 'agua', 'teléfono'],
      'Gastos varios': [],
    };
    const text = items.map(i => (i.descripcion || '').toLowerCase()).join(' ');
    for (const [tipo, words] of Object.entries(keywords)) {
      if (words.some(w => text.includes(w))) return tipo;
    }
    return null;
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

  downloadXml(): void {
    if (!this.document) return;
    this.downloading = true;
    this.dteService.downloadXml(this.document.id).subscribe({
      next: (blob) => {
        this.triggerDownload(blob, `${this.document!.dte_uuid}.xml`);
        this.downloading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.downloading = false;
      }
    });
  }

  downloadAcuse(): void {
    if (!this.document) return;
    this.downloading = true;
    this.dteService.downloadAcuse(this.document.id).subscribe({
      next: (blob) => {
        this.triggerDownload(blob, `${this.document!.dte_uuid}-acuse.xml`);
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

  formatMoney(value: number | null | undefined): string {
    return this.currencyFormat.format(value);
  }

  dteTypeLabel(type: string): string {
    if (this.document?.pais === 'CR') {
      const mapCr: Record<string, string> = {
        '01': 'Factura Electrónica', '02': 'Nota de Débito', '03': 'Nota de Crédito',
        '04': 'Tiquete Electrónico', '08': 'FE Compra', '09': 'FE Exportación',
      };
      return mapCr[type] || type;
    }
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

  setProyecto(proyecto: any): void {
    this.idProyecto = proyecto?.id ?? null;
    this.guardarMetadatos();
  }

  proyectoNombre(id: number): string {
    return this.proyectos.find(p => p.id === id)?.nombre || `#${id}`;
  }

  cambiarDestino(): void {
    this.guardarMetadatos(true);
  }

  guardarMetadatos(silent = false): void {
    if (!this.document || !this.puedeEditarClasificacion || !this.metadataReady) return;

    if (this.metadataSaveTimer) {
      clearTimeout(this.metadataSaveTimer);
    }

    this.metadataSaveTimer = setTimeout(() => {
      this.metadataSaveTimer = null;
      this.guardando = true;
      this.dteService.update(this.document!.id, this.buildMetadataPayload()).subscribe({
        next: (res) => {
          this.document = res.document;
          this.guardando = false;
          if (!silent) {
            this.alertService.success('Éxito', 'Datos actualizados');
          }
        },
        error: (err) => {
          this.guardando = false;
          this.alertService.error(err);
        }
      });
    }, 400);
  }

  private buildMetadataPayload(): DteProcesarPayload {
    return {
      destino: this.destinoSeleccionado,
      id_proyecto: this.idProyecto,
      id_categoria: this.esGasto ? this.idCategoria : null,
      tipo_gasto: this.esGasto ? (this.tipoGasto || null) : null,
      tipo_costo_gasto: this.esCompra ? (this.tipoCostoGasto || null) : null,
    };
  }

  procesar(): void {
    if (!this.document) return;
    this.procesando = true;
    if (this.metadataSaveTimer) {
      clearTimeout(this.metadataSaveTimer);
      this.metadataSaveTimer = null;
    }
    this.dteService.procesar(this.document.id, this.buildMetadataPayload()).subscribe({
      next: (procRes) => {
        this.document = procRes.document || this.document!;
        this.alertService.success('Éxito', procRes.message || this.countryI18n.fe('processedDefault'));
        this.procesando = false;
      },
      error: (err) => {
        this.alertService.error(err?.error?.error || err?.error?.reason || err);
        this.procesando = false;
      }
    });
  }
}
