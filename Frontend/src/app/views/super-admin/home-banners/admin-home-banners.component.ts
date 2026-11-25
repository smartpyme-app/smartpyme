import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

const PAISES_OPCIONES = [
  { codigo: 'SV', etiqueta: 'El Salvador' },
  { codigo: 'HN', etiqueta: 'Honduras' },
  { codigo: 'GT', etiqueta: 'Guatemala' },
  { codigo: 'CR', etiqueta: 'Costa Rica' },
] as const;

@Component({
  selector: 'app-admin-home-banners',
  templateUrl: './admin-home-banners.component.html',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    FormsModule,
    PopoverModule,
    TooltipModule,
    PaginationComponent,
    NotificacionesContainerComponent,
  ],
})
export class AdminHomeBannersComponent extends BaseCrudComponent<any> implements OnInit {
  public readonly paisesOpciones = PAISES_OPCIONES;
  public banners: any = { data: [] };
  public banner: any = {};
  public todosPaises = true;

  constructor(
    apiService: ApiService,
    alertService: AlertService,
    modalManager: ModalManagerService,
  ) {
    super(apiService, alertService, modalManager, {
      endpoint: 'home-banner',
      itemsProperty: 'banners',
      itemProperty: 'banner',
      reloadAfterSave: true,
      reloadAfterDelete: true,
      messages: {
        created: 'El banner fue añadido exitosamente.',
        updated: 'El banner fue guardado exitosamente.',
        deleted: 'El banner fue eliminado exitosamente.',
        createTitle: 'Banner creado',
        updateTitle: 'Banner actualizado',
        deleteTitle: 'Banner eliminado',
        deleteConfirm: '¿Desea eliminar este banner?',
      },
      initNewItem: (item) => {
        item.activo = true;
        item.abrir_nueva_pestana = true;
        item.segundos_visualizacion = 5;
        item.orden = 0;
        item.paises = [];
        item._imagen_desktop_file = null;
        item._imagen_mobile_file = null;
        return item;
      },
    });
  }

  ngOnInit(): void {
    this.filtros.estado = '';
    this.filtros.buscador = '';
    this.filtros.pais = '';
    this.filtros.orden = 'orden';
    this.filtros.direccion = 'asc';
    this.filtros.paginate = 10;
    this.loadAll();
  }

  public override loadAll(): void {
    this.filtros.page = 1;
    this.aplicarFiltros();
  }

  public buscar(): void {
    this.filtros.page = 1;
    this.aplicarFiltros();
  }

  public toggleActivo(item: any): void {
    void this.persistBanner({ ...item, activo: !item.activo });
  }

  public override openModal(
    template: TemplateRef<any>,
    item?: any,
    modalConfig?: Record<string, unknown>,
  ): void {
    let prepared: any | undefined;
    if (item != null && item.id) {
      prepared = { ...item };
      this.todosPaises = !prepared.paises?.length;
      prepared._imagen_desktop_file = null;
      prepared._imagen_mobile_file = null;
      if (prepared.publicado_desde) {
        prepared.publicado_desde = this.toDatetimeLocal(prepared.publicado_desde);
      }
      if (prepared.publicado_hasta) {
        prepared.publicado_hasta = this.toDatetimeLocal(prepared.publicado_hasta);
      }
    } else {
      this.todosPaises = true;
    }

    super.openModal(template, prepared, {
      class: 'modal-xl modal-dialog-scrollable',
      ...modalConfig,
    });
  }

  public override async onSubmit(item?: any): Promise<void> {
    const banner = item ?? this.banner;
    await this.persistBanner(banner);
  }

  public onDesktopFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) {
      this.banner._imagen_desktop_file = file;
    }
  }

  public onMobileFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) {
      this.banner._imagen_mobile_file = file;
    }
  }

  public toggleTodosPaises(): void {
    if (this.todosPaises) {
      this.banner.paises = [];
    }
  }

  public isPaisSelected(codigo: string): boolean {
    return Array.isArray(this.banner.paises) && this.banner.paises.includes(codigo);
  }

  public onPaisChange(codigo: string, checked: boolean): void {
    this.todosPaises = false;
    const current: string[] = Array.isArray(this.banner.paises) ? [...this.banner.paises] : [];
    if (checked) {
      if (!current.includes(codigo)) {
        current.push(codigo);
      }
    } else {
      this.banner.paises = current.filter((c) => c !== codigo);
      return;
    }
    this.banner.paises = current;
  }

  public paisesLabel(paises: string[] | null | undefined): string {
    if (!paises?.length) {
      return 'Todos';
    }
    return paises
      .map((c) => this.paisesOpciones.find((p) => p.codigo === c)?.etiqueta ?? c)
      .join(', ');
  }

  private toDatetimeLocal(value: string): string {
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
      return '';
    }
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  public imgUrl(path: string | null | undefined): string {
    if (!path) {
      return '';
    }
    const normalized = path.startsWith('/') ? path.slice(1) : path;
    return `${this.apiService.baseUrl}/img/${normalized}`;
  }

  protected aplicarFiltros(): void {
    this.loading = true;
    this.apiService
      .getAll('home-banners/admin', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (banners) => {
          this.banners = banners;
          this.loading = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.loading = false;
        },
      });
  }

  private async persistBanner(banner: any): Promise<void> {
    if (!banner.nombre?.trim()) {
      this.alertService.error('El nombre es requerido.');
      return;
    }
    if (!banner.id && !banner._imagen_desktop_file) {
      this.alertService.error('La imagen de escritorio es requerida.');
      return;
    }

    const fd = new FormData();
    if (banner.id) {
      fd.append('id', String(banner.id));
    }
    fd.append('nombre', banner.nombre.trim());
    fd.append(
      'paises',
      JSON.stringify(this.todosPaises ? [] : banner.paises ?? []),
    );
    if (banner.url_destino) {
      fd.append('url_destino', banner.url_destino);
    }
    fd.append('abrir_nueva_pestana', banner.abrir_nueva_pestana ? '1' : '0');
    fd.append('segundos_visualizacion', String(banner.segundos_visualizacion ?? 5));
    fd.append('orden', String(banner.orden ?? 0));
    fd.append('activo', banner.activo ? '1' : '0');
    if (banner.publicado_desde) {
      fd.append('publicado_desde', banner.publicado_desde);
    }
    if (banner.publicado_hasta) {
      fd.append('publicado_hasta', banner.publicado_hasta);
    }
    if (banner._imagen_desktop_file) {
      fd.append('imagen_desktop', banner._imagen_desktop_file);
    }
    if (banner._imagen_mobile_file) {
      fd.append('imagen_mobile', banner._imagen_mobile_file);
    }

    this.saving = true;
    const isNew = !banner.id;
    try {
      const saved = await this.apiService
        .store('home-banner', fd)
        .pipe(this.untilDestroyed())
        .toPromise();
      this.alertService.success(
        isNew
          ? this.config.messages!.createTitle!
          : this.config.messages!.updateTitle!,
        isNew
          ? this.config.messages!.created!
          : this.config.messages!.updated!,
      );
      this.banner = saved;
      this.closeModal();
      if (this.config.reloadAfterSave) {
        this.aplicarFiltros();
      }
    } catch (error) {
      this.alertService.error(error);
    } finally {
      this.saving = false;
    }
  }
}