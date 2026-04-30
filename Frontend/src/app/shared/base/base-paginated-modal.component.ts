import { Directive, OnDestroy, TemplateRef } from '@angular/core';
import { MonoTypeOperatorFunction, Observable, Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService, ModalConfig } from '@services/modal-manager.service';
import type { PaginatedResponse } from '../../models/shared/Pagination.interface';

export type { PaginatedResponse };

/**
 * Clase base para listados con modal: filtros, carga, cierre y `takeUntil` al destruir.
 */
@Directive()
export abstract class BasePaginatedModalComponent implements OnDestroy {
  /** Filtros usados con `getAll` / listados. Las subclases completan estructura. */
  public filtros: any = {
    buscador: '',
    paginate: 10,
  };

  public loading = false;
  public saving = false;
  public modalRef?: BsModalRef;

  private readonly destroy$ = new Subject<void>();

  constructor(
    protected apiService: ApiService,
    protected alertService: AlertService,
    protected modalManager: ModalManagerService
  ) {}

  public ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  /**
   * Operador reutilizable en pipes RxJS: cancela al destruir el componente.
   */
  public untilDestroyed<T>(): MonoTypeOperatorFunction<T> {
    return (source: Observable<T>) => source.pipe(takeUntil(this.destroy$));
  }

  public closeModal(): void {
    this.modalRef?.hide();
    this.modalRef = undefined;
  }

  /**
   * Abre un modal (plantilla) con opciones estándar de ngx-bootstrap.
   */
  public openModal(
    template: TemplateRef<any>,
    options?: { class?: string; backdrop?: boolean | 'static'; keyboard?: boolean }
  ): void {
    this.modalRef = this.modalManager.show(template, {
      class: 'modal-lg',
      backdrop: true,
      ...options,
    });
  }

  /**
   * Modal grande con `ngx-bootstrap`, alineado con `BaseModalComponent` / `BaseFilteredPaginatedModalComponent`.
   */
  public openLargeModal(template: TemplateRef<any>, config?: ModalConfig): void {
    this.modalRef = this.modalManager.openModal(template, {
      size: 'lg',
      backdrop: 'static',
      ...config,
    });
  }

  /**
   * Modal solo por configuración (p. ej. reportes) sin tocar un “registro” asociado.
   */
  protected openModalConfig(template: TemplateRef<any>, config?: any): void {
    this.modalRef = this.modalManager.show(template, config);
  }

  /**
   * Igual que `BasePaginatedComponent.setPagination`: paginar vía URL en `path` de Laravel.
   */
  public setPagination(event: any): void {
    if (!event || typeof event.page === 'undefined') {
      console.error('Evento de paginación inválido:', event);
      return;
    }

    this.loading = true;

    const paginatedData = this.getPaginatedData();

    if (!paginatedData) {
      console.error(`${this.constructor.name}: getPaginatedData() retornó null/undefined`);
      this.loading = false;
      return;
    }

    if (!paginatedData.path) {
      console.error(
        `${this.constructor.name}: El objeto de datos paginados no tiene 'path'.`,
        'Estructura:',
        paginatedData
      );
      this.loading = false;
      return;
    }

    const paginationUrl = `${paginatedData.path}?page=${event.page}`;

    this.apiService
      .paginate(paginationUrl, this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          this.setPaginatedData(response);
          this.onPaginateSuccess(response);
          this.loading = false;
        },
        error: (error) => {
          console.error(`${this.constructor.name}: Error en paginación`, error);
          this.alertService.error(error);
          this.loading = false;
        },
      });
  }

  protected abstract getPaginatedData(): PaginatedResponse | null;
  protected abstract setPaginatedData(data: PaginatedResponse): void;

  protected onPaginateSuccess(_response: PaginatedResponse): void {
    // opcional
  }
}
