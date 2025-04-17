import { DestroyRef, inject } from '@angular/core';
import { subscriptionHelper } from '../utils/subscription.helper';

/**
 * Clase base para componentes que necesitan gestionar suscripciones de forma segura.
 * 
 * Proporciona un método helper para usar con takeUntil que se desuscribe automáticamente
 * cuando el componente es destruido, previniendo memory leaks.
 * 
 * Uso:
 * ```typescript
 * import { Component, OnInit } from '@angular/core';
 * import { BaseComponent } from '@shared/base/base.component';
 * 
 * @Component({...})
 * export class MiComponente extends BaseComponent implements OnInit {
 *   ngOnInit() {
 *     this.apiService.getData()
 *       .pipe(this.untilDestroyed())
 *       .subscribe(data => {
 *         // Manejar datos - se desuscribe automáticamente al destruir el componente
 *       });
 *   }
 * }
 * ```
 */
export abstract class BaseComponent {
  protected readonly destroyRef: DestroyRef;
  protected readonly untilDestroyed: ReturnType<typeof subscriptionHelper>;

  constructor() {
    // inject() aquí asegura contexto de inyección válido (p. ej. Angular 19/20 + herencia).
    this.destroyRef = inject(DestroyRef);
    this.untilDestroyed = subscriptionHelper(this.destroyRef);
  }
}

