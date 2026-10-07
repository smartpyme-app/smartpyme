import {
  Component,
  DestroyRef,
  ElementRef,
  HostListener,
  inject,
  OnInit,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import {
  ContadorEmpresaPortafolio,
  ContadoresPortalService,
} from '@services/contadores-portal.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
  selector: 'app-contador-empresa-bar',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './contador-empresa-bar.component.html',
  styleUrls: ['./contador-empresa-bar.component.css'],
})
export class ContadorEmpresaBarComponent implements OnInit {
  empresas: ContadorEmpresaPortafolio[] = [];
  empresaId: number | null = null;
  visible = false;
  cambiando = false;
  menuAbierto = false;

  private destroyRef = inject(DestroyRef);
  private host = inject(ElementRef<HTMLElement>);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private api: ApiService,
    private contadoresPortal: ContadoresPortalService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.visible = this.api.esPortalContador();
    if (!this.visible) {
      return;
    }

    this.syncEmpresaId();

    this.contadoresPortal
      .listarEmpresas()
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (res) => {
          this.empresas = res?.empresas ?? [];
          if (!this.empresaId && this.empresas.length) {
            this.empresaId = this.empresas[0].id;
          }
        },
      });

    this.contadoresPortal.empresaActiva$.pipe(this.untilDestroyed()).subscribe((id) => {
      this.empresaId = id;
    });

    this.contadoresPortal.contextoSesionActualizado$
      .pipe(this.untilDestroyed())
      .subscribe(() => this.syncEmpresaId());
  }

  get empresaActiva(): ContadorEmpresaPortafolio | null {
    if (this.empresaId == null) {
      return null;
    }
    const found = this.empresas.find((e) => e.id === this.empresaId);
    if (found) {
      return found;
    }
    const ctx = this.contadoresPortal.leerContextoEmpresa();
    if (ctx?.id === this.empresaId) {
      return {
        acceso_id: ctx.acceso_id,
        id: ctx.id,
        nombre: ctx.nombre,
        logo: ctx.logo,
        giro: ctx.giro,
        permisos: ctx.permisos ?? [],
      };
    }
    const user = this.api.auth_user();
    if (user?.empresa?.id === this.empresaId) {
      return {
        acceso_id: 0,
        id: user.empresa.id,
        nombre: user.empresa.nombre,
        logo: user.empresa.logo ?? null,
        permisos: [],
      };
    }
    return null;
  }

  get nombreMostrado(): string {
    return this.empresaActiva?.nombre ?? 'Seleccionar empresa';
  }

  toggleMenu(): void {
    if (this.cambiando || !this.empresas.length) {
      return;
    }
    this.menuAbierto = !this.menuAbierto;
  }

  cerrarMenu(): void {
    this.menuAbierto = false;
  }

  seleccionar(id: number): void {
    this.cerrarMenu();
    this.onEmpresaChange(id);
  }

  onEmpresaChange(id: number): void {
    if (!Number.isFinite(id) || this.cambiando || id === this.empresaId) {
      return;
    }
    this.cambiando = true;
    this.contadoresPortal.cambiarEmpresaActiva(id).subscribe({
      next: () => {
        this.cambiando = false;
        this.empresaId = id;
        if (this.router.url.startsWith('/despacho')) {
          this.router.navigate([], {
            queryParams: { empresa: id },
            queryParamsHandling: 'merge',
          });
        }
      },
      error: () => {
        this.cambiando = false;
      },
    });
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

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    if (!this.menuAbierto) {
      return;
    }
    const target = event.target as Node | null;
    if (target && !this.host.nativeElement.contains(target)) {
      this.cerrarMenu();
    }
  }

  private syncEmpresaId(): void {
    this.empresaId =
      this.contadoresPortal.leerContextoEmpresa()?.id ??
      this.api.auth_user()?.id_empresa ??
      null;
  }
}
