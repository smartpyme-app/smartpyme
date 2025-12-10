import { TemplateRef, inject } from '@angular/core';
import { BaseFilteredPaginatedModalComponent } from './base-filtered-paginated-modal.component';
import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';
import { ModalManagerService } from '../../services/modal-manager.service';
import { HttpCacheService } from '../../services/http-cache.service';

/**
 * Configuración para el componente CRUD base
 */
export interface CrudConfig<T = any> {
  /** Endpoint base para las operaciones CRUD (ej: 'categoria', 'bodega', 'canal') */
  endpoint: string;
  
  /** Nombre de la propiedad que contiene la lista de items (ej: 'categorias', 'bodegas') */
  itemsProperty: string;
  
  /** Nombre de la propiedad que contiene el item actual (ej: 'categoria', 'bodega') */
  itemProperty: string;
  
  /** Mensajes personalizados */
  messages?: {
    created?: string;
    updated?: string;
    deleted?: string;
    createTitle?: string;
    updateTitle?: string;
    deleteTitle?: string;
    deleteConfirm?: string;
  };
  
  /** Callback opcional antes de guardar (permite validaciones o transformaciones) */
  beforeSave?: (item: T) => T | Promise<T>;
  
  /** Callback opcional después de guardar */
  afterSave?: (item: T, isNew: boolean) => void;
  
  /** Callback opcional después de eliminar */
  afterDelete?: (item: T) => void;
  
  /** Callback opcional para inicializar un nuevo item */
  initNewItem?: (item: T) => T;
  
  /** Si es true, recarga la lista después de guardar. Si es false, actualiza manualmente */
  reloadAfterSave?: boolean;
  
  /** Si es true, recarga la lista después de eliminar. Si es false, elimina del array manualmente */
  reloadAfterDelete?: boolean;
}

/**
 * Componente base genérico para operaciones CRUD estándar.
 * 
 * Elimina la duplicación masiva de los métodos loadAll(), delete(), onSubmit() 
 * que se repiten en cientos de componentes.
 * 
 * Uso básico:
 * ```typescript
 * export class CategoriasComponent extends BaseCrudComponent<any> {
 *   public categorias: any = {};
 *   public categoria: any = {};
 * 
 *   constructor(
 *     apiService: ApiService,
 *     alertService: AlertService,
 *     modalManager: ModalManagerService
 *   ) {
 *     super(apiService, alertService, modalManager, {
 *       endpoint: 'categoria',
 *       itemsProperty: 'categorias',
 *       itemProperty: 'categoria'
 *     });
 *   }
 * 
 *   protected aplicarFiltros(): void {
 *     this.loading = true;
 *     this.apiService.getAll('categorias', this.filtros)
 *       .pipe(this.untilDestroyed())
 *       .subscribe((response: any) => {
 *         this.categorias = response;
 *         this.loading = false;
 *       }, error => {
 *         this.alertService.error(error);
 *         this.loading = false;
 *       });
 *   }
 * }
 * ```
 * 
 * Uso avanzado con callbacks:
 * ```typescript
 * export class MiComponente extends BaseCrudComponent<MiModelo> {
 *   constructor(...) {
 *     super(apiService, alertService, modalManager, {
 *       endpoint: 'mi-endpoint',
 *       itemsProperty: 'items',
 *       itemProperty: 'item',
 *       beforeSave: (item) => {
 *         // Validaciones o transformaciones antes de guardar
 *         item.fecha_actualizacion = new Date();
 *         return item;
 *       },
 *       afterSave: (item, isNew) => {
 *         // Lógica adicional después de guardar
 *         if (isNew) {
 *           this.actualizarContadores();
 *         }
 *       },
 *       initNewItem: (item) => {
 *         item.id_empresa = this.apiService.auth_user().id_empresa;
 *         item.enable = true;
 *         return item;
 *       }
 *     });
 *   }
 * }
 * ```
 */
export abstract class BaseCrudComponent<T = any> extends BaseFilteredPaginatedModalComponent {
  protected config: CrudConfig<T>;
  protected cacheService = inject(HttpCacheService);
  
  constructor(
    protected override apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    config: CrudConfig<T>
  ) {
    super(apiService, alertService, modalManager);
    // Mensajes por defecto
    const defaultMessages = {
      created: 'Registro creado exitosamente.',
      updated: 'Registro guardado exitosamente.',
      deleted: 'Registro eliminado exitosamente.',
      createTitle: 'Registro creado',
      updateTitle: 'Registro guardado',
      deleteTitle: 'Registro eliminado',
      deleteConfirm: '¿Desea eliminar el Registro?'
    };

    this.config = {
      reloadAfterSave: true,
      reloadAfterDelete: true,
      ...config,
      messages: {
        ...defaultMessages,
        ...config.messages
      }
    };
  }

  /**
   * Carga todos los registros, reseteando los filtros a valores por defecto.
   * Este método puede ser sobrescrito para personalizar el comportamiento.
   */
  public loadAll(): void {
    this.loading = true;
    // Resetear filtros comunes
    if (this.filtros) {
      this.filtros.estado = '';
      this.filtros.buscador = '';
      this.filtros.page = 1;
      if (this.filtros.paginate === undefined) {
        this.filtros.paginate = 10;
      }
    }
    this.aplicarFiltros();
  }

  /**
   * Guarda o actualiza un registro.
   * Maneja automáticamente la lógica de creación vs actualización.
   * 
   * @param item - Item opcional a guardar. Si no se proporciona, usa el item del componente.
   * @param isStatusChange - Indica si es un cambio de estado (para mensajes personalizados)
   */
  public async onSubmit(item?: T, isStatusChange: boolean = false): Promise<void> {
    const itemToSave = item || (this as any)[this.config.itemProperty];
    
    if (!itemToSave) {
      console.error(`${this.constructor.name}: No se encontró el item a guardar en la propiedad '${this.config.itemProperty}'`);
      return;
    }

    this.loading = true;
    this.saving = true;

    try {
      // Aplicar callback beforeSave si existe
      let processedItem = itemToSave;
      if (this.config.beforeSave) {
        processedItem = await this.config.beforeSave(itemToSave);
      }

      // Guardar o actualizar
      const isNew = !processedItem.id;
      const savedItem = await this.apiService.store(this.config.endpoint, processedItem)
        .pipe(this.untilDestroyed())
        .toPromise();

      // Invalidar cache del item específico si se está editando
      if (!isNew && savedItem?.id) {
        const itemUrl = `${this.config.endpoint}/${savedItem.id}`;
        this.cacheService.delete(itemUrl);
        // También invalidar el patrón de listas relacionadas
        this.cacheService.invalidatePattern(`/${this.config.endpoint}s`);
        this.cacheService.invalidatePattern(`/${this.config.endpoint}`);
      }

      // Invalidar cache de listas relacionadas
      this.cacheService.invalidatePattern(`/${this.config.endpoint}s`);
      this.cacheService.invalidatePattern(`/${this.config.endpoint}`);

      // Actualizar el item en el componente
      (this as any)[this.config.itemProperty] = savedItem;

      // Manejar la lista de items
      if (isStatusChange) {
        // Si es cambio de estado, actualizar el item en la lista
        this.updateItemInList(savedItem);
      } else {
        if (isNew) {
          // Si es nuevo, agregarlo a la lista
          this.addItemToList(savedItem);
        } else {
          // Si es actualización, actualizar en la lista
          this.updateItemInList(savedItem);
        }
      }

      // Mostrar mensaje de éxito
      if (!isStatusChange) {
        const title = isNew ? this.config.messages!.createTitle! : this.config.messages!.updateTitle!;
        const message = isNew ? this.config.messages!.created! : this.config.messages!.updated!;
        this.alertService.success(title, message);
      }

      // Recargar lista si está configurado
      if (this.config.reloadAfterSave) {
        this.aplicarFiltros();
      }

      // Ejecutar callback afterSave si existe
      if (this.config.afterSave) {
        this.config.afterSave(savedItem, isNew);
      }

      // Cerrar modal si existe
      if (this.modalRef) {
        this.closeModal();
      }

    } catch (error: any) {
      this.alertService.error(error);
    } finally {
      this.loading = false;
      this.saving = false;
    }
  }

  /**
   * Elimina un registro.
   * 
   * @param item - Item a eliminar (debe tener propiedad 'id')
   */
  public delete(item: T | number): void {
    const itemToDelete = typeof item === 'number' ? item : (item as any).id;
    
    if (!itemToDelete) {
      console.error(`${this.constructor.name}: No se pudo obtener el ID del item a eliminar`);
      return;
    }

    if (!confirm(this.config.messages!.deleteConfirm!)) {
      return;
    }

    // Invalidar cache antes de eliminar
    const itemUrl = `${this.config.endpoint}/${itemToDelete}`;
    this.cacheService.delete(itemUrl);
    this.cacheService.invalidatePattern(`/${this.config.endpoint}s`);
    this.cacheService.invalidatePattern(`/${this.config.endpoint}`);

    this.loading = true;

    this.apiService.delete(this.config.endpoint + '/', itemToDelete)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (deletedItem: any) => {
          // Eliminar de la lista si está configurado
          if (this.config.reloadAfterDelete) {
            this.aplicarFiltros();
          } else {
            this.removeItemFromList(deletedItem.id);
          }

          // Ejecutar callback afterDelete si existe
          if (this.config.afterDelete && typeof item !== 'number') {
            this.config.afterDelete(item as T);
          }

          this.alertService.success(
            this.config.messages!.deleteTitle!,
            this.config.messages!.deleted!
          );
        },
        error: (error: any) => {
          this.alertService.error(error);
          this.loading = false;
        }
      });
  }

  /**
   * Abre el modal de edición/creación.
   * Inicializa el item según la configuración.
   * 
   * @param template - TemplateRef del modal
   * @param item - Item opcional a editar. Si no se proporciona, crea uno nuevo.
   * @param modalConfig - Configuración opcional del modal (tamaño, backdrop, etc.)
   */
  public override openModal(template: TemplateRef<any>, item?: T, modalConfig?: any): void {
    const itemProperty = this.config.itemProperty;
    
    if (item) {
      // Copiar el item para evitar mutaciones
      (this as any)[itemProperty] = { ...item };
    } else {
      // Crear nuevo item
      let newItem: any = {};
      
      // Aplicar callback initNewItem si existe
      if (this.config.initNewItem) {
        newItem = this.config.initNewItem(newItem);
      } else {
        // Valores por defecto comunes
        newItem.id_empresa = this.apiService.auth_user()?.id_empresa;
        newItem.enable = true;
        newItem.activo = 1;
      }
      
      (this as any)[itemProperty] = newItem;
    }

    super.openModal(template, modalConfig);
  }

  /**
   * Agrega un item a la lista.
   * Maneja tanto arrays simples como objetos con propiedad 'data' (paginación Laravel).
   */
  protected addItemToList(item: T): void {
    const items = (this as any)[this.config.itemsProperty];
    
    if (Array.isArray(items)) {
      items.push(item);
    } else if (items?.data && Array.isArray(items.data)) {
      items.data.push(item);
    }
  }

  /**
   * Actualiza un item en la lista.
   * Busca por ID y reemplaza el item.
   */
  protected updateItemInList(item: T): void {
    const items = (this as any)[this.config.itemsProperty];
    const itemId = (item as any).id;
    
    if (!itemId) return;

    if (Array.isArray(items)) {
      const index = items.findIndex((i: any) => i.id === itemId);
      if (index !== -1) {
        items[index] = item;
      }
    } else if (items?.data && Array.isArray(items.data)) {
      const index = items.data.findIndex((i: any) => i.id === itemId);
      if (index !== -1) {
        items.data[index] = item;
      }
    }
  }

  /**
   * Elimina un item de la lista.
   * Busca por ID y lo elimina del array.
   */
  protected removeItemFromList(itemId: number): void {
    const items = (this as any)[this.config.itemsProperty];
    
    if (Array.isArray(items)) {
      const index = items.findIndex((i: any) => i.id === itemId);
      if (index !== -1) {
        items.splice(index, 1);
      }
    } else if (items?.data && Array.isArray(items.data)) {
      const index = items.data.findIndex((i: any) => i.id === itemId);
      if (index !== -1) {
        items.data.splice(index, 1);
      }
    }
  }
}

