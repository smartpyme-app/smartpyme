import { ChangeDetectorRef, Component, DestroyRef, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { forkJoin } from 'rxjs';
import { ApiService } from '@services/api.service';
import {
  ContadorCumplimientoResponse,
  ContadorEmpresaPortafolio,
  ContadoresPortalService,
  EstadoCalendarioContador,
  EstadoDocumentoContador,
} from '@services/contadores-portal.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { opcionesPeriodo, periodoCierrePorDefecto } from './contadores-periodo.util';

@Component({
  selector: 'app-contadores-cumplimiento',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './contadores-cumplimiento.component.html',
  styleUrls: ['./contadores-dashboard.component.css', './contadores-cumplimiento.component.css'],
})
export class ContadoresCumplimientoComponent implements OnInit {
  usuario: { name?: string; empresa?: { nombre?: string } } | null = null;
  idDocumentoPendiente: number | null = null;
  slugCarga = 'libre';
  modalCargaAbierto = false;
  docCargaNombreCatalogo = '';
  nombreCarga = '';
  venceEnCarga = '';
  archivoPendiente: File | null = null;
  subiendo = false;
  empresas: ContadorEmpresaPortafolio[] = [];
  vista: ContadorCumplimientoResponse | null = null;

  idEmpresa: number | null = null;
  periodoMes = periodoCierrePorDefecto().mes;
  periodoAnio = periodoCierrePorDefecto().anio;
  readonly opcionesPeriodo = opcionesPeriodo(24);

  loading = true;
  error = '';
  readonly logoFallo = new Set<number>();

  private destroyRef = inject(DestroyRef);
  private cdr = inject(ChangeDetectorRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private api: ApiService,
    private contadoresPortal: ContadoresPortalService,
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.usuario = this.api.auth_user();

    this.route.queryParamMap.pipe(this.untilDestroyed()).subscribe((params) => {
      const def = periodoCierrePorDefecto();
      const mes = Number(params.get('mes'));
      const anio = Number(params.get('anio'));
      this.periodoMes = Number.isFinite(mes) ? mes : def.mes;
      this.periodoAnio = Number.isFinite(anio) ? anio : def.anio;

      const id = Number(params.get('empresa'));
      this.idEmpresa = Number.isFinite(id) ? id : null;
      this.cargar();
    });
  }

  get despachoNombre(): string {
    return this.usuario?.empresa?.nombre ?? 'Despacho contable';
  }

  get periodoKey(): string {
    return `${this.periodoMes}-${this.periodoAnio}`;
  }

  get empresaSeleccionada(): ContadorEmpresaPortafolio | null {
    if (!this.idEmpresa) {
      return null;
    }
    return this.empresas.find((e) => e.id === this.idEmpresa) ?? null;
  }

  get puedeSubirArchivos(): boolean {
    return !!this.vista?.capacidades?.subir_archivos;
  }

  /** Tipos del catálogo (una opción por slug) para el modal de alta. */
  get tiposCatalogoModal(): { slug: string; nombre: string }[] {
    const seen = new Set<string>();
    const out: { slug: string; nombre: string }[] = [];
    for (const d of this.vista?.documentos ?? []) {
      if (!d.slug || d.es_otro || seen.has(d.slug) || d.subible === false) {
        continue;
      }
      seen.add(d.slug);
      out.push({ slug: d.slug, nombre: d.nombre_catalogo ?? d.nombre });
    }
    return out;
  }

  onEmpresaChange(id: number): void {
    if (!Number.isFinite(id)) {
      return;
    }
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { empresa: id, mes: this.periodoMes, anio: this.periodoAnio },
      queryParamsHandling: 'merge',
    });
  }

  onPeriodoKeyChange(key: string): void {
    const [m, a] = key.split('-').map(Number);
    if (!Number.isFinite(m) || !Number.isFinite(a)) {
      return;
    }
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { mes: m, anio: a },
      queryParamsHandling: 'merge',
    });
  }

  volverCartera(): void {
    this.router.navigate(['/contadores/cartera'], {
      queryParams: {
        empresa: this.idEmpresa,
        mes: this.periodoMes,
        anio: this.periodoAnio,
      },
    });
  }

  cerrarSesion(): void {
    this.api.logout();
    this.router.navigate(['/login']);
  }

  etiquetaDocumento(estado: EstadoDocumentoContador): string {
    const map: Record<EstadoDocumentoContador, string> = {
      vigente: 'Vigente',
      por_vencer: 'Por vencer',
      sin_cargar: 'Sin cargar',
    };
    return map[estado];
  }

  etiquetaCalendario(estado: EstadoCalendarioContador): string {
    return estado === 'presentado' ? 'Presentado' : 'Pendiente';
  }

  etiquetaRenovacion(estado: string): string {
    if (estado === 'vence_pronto') {
      return 'Vence pronto';
    }
    if (estado === 'vigente') {
      return 'Vigente';
    }
    return estado;
  }

  formatoMoneda(monto: number | null | undefined): string {
    if (monto == null) {
      return '—';
    }
    return new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }).format(monto);
  }

  iniciales(nombre: string): string {
    const parts = (nombre || '?').trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return (nombre || '?').trim().slice(0, 2).toUpperCase();
  }

  colorMarca(nombre: string): string {
    const palette = ['#1775e5', '#0d9488', '#6366f1', '#ea580c', '#7c3aed', '#0891b2'];
    let h = 0;
    for (let i = 0; i < nombre.length; i++) {
      h = (h + nombre.charCodeAt(i)) % palette.length;
    }
    return palette[h];
  }

  logoUrl(logo: string | null | undefined): string | null {
    if (!logo?.trim()) {
      return null;
    }
    const path = logo.trim();
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    return `${this.api.baseUrl}/img/${path}`;
  }

  mostrarLogo(id: number, logo: string | null | undefined): boolean {
    return !!this.logoUrl(logo) && !this.logoFallo.has(id);
  }

  onLogoError(id: number): void {
    this.logoFallo.add(id);
  }

  urlArchivo(ruta: string | null | undefined): string | null {
    if (!ruta?.trim()) {
      return null;
    }
    const token = this.api.auth_token();
    const sep = ruta.includes('?') ? '&' : '?';
    return `${this.api.baseUrl}/img/${ruta}${token ? `${sep}token=${token}` : ''}`;
  }

  abrirArchivo(doc: { archivo_url: string | null }): void {
    const url = this.urlArchivo(doc.archivo_url);
    if (url) {
      window.open(url, '_blank', 'noopener');
    }
  }

  trackDocumento(doc: { id: number | null; slug: string | null; es_anexo?: boolean }, index: number): string {
    if (doc.id != null) {
      return String(doc.id);
    }
    return `${doc.slug ?? 'slot'}-${index}`;
  }

  abrirSelectorArchivoGeneral(): void {
    const slot = this.vista?.documentos.find(
      (d) => !d.id && d.subible !== false && d.estado === 'sin_cargar' && !d.es_otro,
    );
    if (slot?.slug) {
      this.agregarDocumento(slot.slug, slot.nombre);
      return;
    }
    this.agregarDocumento();
  }

  cargarEnSlot(doc: { slug: string | null; nombre: string; subible?: boolean }): void {
    if (!doc.slug || doc.subible === false) {
      return;
    }
    this.agregarDocumento(doc.slug, doc.nombre);
  }

  agregarOtroDelTipo(doc: { slug: string | null; nombre_catalogo?: string | null; nombre: string }): void {
    if (!doc.slug) {
      return;
    }
    this.agregarDocumento(doc.slug, doc.nombre_catalogo ?? doc.nombre);
  }

  agregarDocumento(slug?: string, nombreSugerido?: string): void {
    if (!this.vista?.capacidades?.subir_archivos || !this.idEmpresa) {
      return;
    }
    this.idDocumentoPendiente = null;
    this.slugCarga = slug ?? 'libre';
    this.docCargaNombreCatalogo =
      slug && slug !== 'libre' ? (nombreSugerido ?? slug) : 'Documento personalizado';
    this.nombreCarga = nombreSugerido ?? (slug && slug !== 'libre' ? this.docCargaNombreCatalogo : '');
    this.venceEnCarga = '';
    this.archivoPendiente = null;
    this.modalCargaAbierto = true;
  }

  editarDocumento(doc: {
    id: number | null;
    slug: string | null;
    nombre: string;
    nombre_catalogo?: string | null;
    vence_en?: string | null;
  }): void {
    if (!this.vista?.capacidades?.subir_archivos || !this.idEmpresa || doc.id == null) {
      return;
    }
    this.idDocumentoPendiente = doc.id;
    this.slugCarga = doc.slug ?? 'libre';
    this.docCargaNombreCatalogo = doc.nombre_catalogo ?? (doc.slug ? doc.nombre : 'Documento personalizado');
    this.nombreCarga = doc.nombre;
    this.venceEnCarga = doc.vence_en ?? '';
    this.archivoPendiente = null;
    this.modalCargaAbierto = true;
  }

  onSlugCargaChange(): void {
    if (this.idDocumentoPendiente) {
      return;
    }
    if (this.slugCarga === 'libre') {
      return;
    }
    const slot = this.vista?.documentos.find((d) => d.slug === this.slugCarga && !d.es_anexo && !d.es_otro);
    if (slot) {
      this.docCargaNombreCatalogo = slot.nombre_catalogo ?? slot.nombre;
      if (!this.nombreCarga.trim()) {
        this.nombreCarga = slot.nombre_catalogo ?? slot.nombre;
      }
    }
  }

  get esEdicionDocumento(): boolean {
    return this.idDocumentoPendiente != null;
  }

  cerrarModalCarga(): void {
    this.modalCargaAbierto = false;
    this.idDocumentoPendiente = null;
    this.slugCarga = 'libre';
    this.docCargaNombreCatalogo = '';
    this.nombreCarga = '';
    this.venceEnCarga = '';
    this.archivoPendiente = null;
  }

  confirmarSubidaModal(): void {
    const file = this.archivoPendiente;
    if (!this.idEmpresa) {
      return;
    }
    if (!file) {
      window.alert('Seleccione un archivo PDF o imagen antes de subir.');
      return;
    }
    if (this.slugCarga === 'libre' && !this.nombreCarga.trim()) {
      window.alert('Indique un nombre para el documento.');
      return;
    }
    this.enviarArchivo(file);
  }

  onArchivoSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || !this.modalCargaAbierto) {
      return;
    }
    this.archivoPendiente = file;
    this.cdr.markForCheck();
  }

  onDropArchivos(event: DragEvent): void {
    event.preventDefault();
    if (!this.vista?.capacidades?.subir_archivos || !this.idEmpresa) {
      return;
    }
    const file = event.dataTransfer?.files?.[0];
    if (!file) {
      return;
    }
    this.abrirSelectorArchivoGeneral();
    this.archivoPendiente = file;
  }

  private enviarArchivo(file: File): void {
    if (!this.idEmpresa) {
      return;
    }
    this.subiendo = true;
    this.contadoresPortal
      .subirDocumentoCumplimiento(this.idEmpresa, file, {
        idDocumento: this.idDocumentoPendiente,
        slug: this.slugCarga,
        venceEn: this.venceEnCarga || null,
        titulo: this.nombreCarga || null,
      })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => {
          this.subiendo = false;
          this.cerrarModalCarga();
          this.cargar();
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.subiendo = false;
          const raw = err?.error?.error;
          const msg = Array.isArray(raw) ? raw.join(', ') : (raw ?? 'No se pudo subir el archivo.');
          window.alert(msg);
          this.cdr.markForCheck();
        },
      });
  }

  marcarPresentado(codigo: string): void {
    if (!this.vista?.capacidades?.marcar_presentado || !this.idEmpresa) {
      return;
    }
    this.contadoresPortal
      .marcarObligacionPresentada(this.idEmpresa, codigo, this.periodoMes, this.periodoAnio)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: () => this.cargar(),
      });
  }

  private cargar(): void {
    this.loading = true;
    this.error = '';
    this.vista = null;

    forkJoin({
      empresas: this.contadoresPortal.listarEmpresas(),
      ...(this.idEmpresa
        ? {
            cumplimiento: this.contadoresPortal.cumplimiento(this.idEmpresa, {
              mes: this.periodoMes,
              anio: this.periodoAnio,
            }),
          }
        : {}),
    })
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (res) => {
          this.empresas = res.empresas?.empresas ?? [];
          if (!this.idEmpresa && this.empresas.length) {
            this.idEmpresa = this.empresas[0].id;
            this.loading = false;
            this.router.navigate([], {
              relativeTo: this.route,
              queryParams: { empresa: this.idEmpresa, mes: this.periodoMes, anio: this.periodoAnio },
              queryParamsHandling: 'merge',
              replaceUrl: true,
            });
            return;
          }
          if ('cumplimiento' in res && res.cumplimiento) {
            this.vista = res.cumplimiento;
            if (this.idEmpresa) {
              this.contadoresPortal.establecerContextoEmpresa(this.idEmpresa).pipe(this.untilDestroyed()).subscribe();
            }
          }
          this.loading = false;
          if (!this.idEmpresa) {
            this.error = 'Elige una empresa desde la cartera para ver cumplimiento.';
          }
        },
        error: (err) => {
          this.loading = false;
          const raw = err?.error?.error;
          this.error = Array.isArray(raw) ? raw.join(', ') : (raw ?? 'No se pudo cargar cumplimiento.');
        },
      });
  }
}
