import { Directive, OnDestroy, TemplateRef } from '@angular/core';
import { MonoTypeOperatorFunction, Observable, Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';

export interface PaginatedResponse {
  data: unknown[];
  current_page?: number;
  per_page?: number;
  last_page?: number;
  total?: number;
  totales_generales?: unknown;
  [key: string]: unknown;
}

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
   * Modal solo por configuración (p. ej. reportes) sin tocar un “registro” asociado.
   */
  protected openModalConfig(template: TemplateRef<any>, config?: any): void {
    this.modalRef = this.modalManager.show(template, config);
  }

  protected abstract getPaginatedData(): PaginatedResponse | null;
  protected abstract setPaginatedData(data: PaginatedResponse): void;

  protected onPaginateSuccess(_response: PaginatedResponse): void {
    // opcional
  }
}
