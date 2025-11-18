import { DestroyRef, inject } from '@angular/core';
import { Subject, Observable, takeUntil } from 'rxjs';

/**
 * Helper para gestionar suscripciones de forma segura usando DestroyRef (Angular 16+)
 * 
 * Este helper previene memory leaks al desuscribirse automáticamente cuando
 * el componente es destruido.
 * 
 * Uso:
 * ```typescript
 * import { DestroyRef, inject } from '@angular/core';
 * import { subscriptionHelper } from '@shared/utils/subscription.helper';
 * 
 * export class MiComponente {
 *   private destroyRef = inject(DestroyRef);
 *   private untilDestroyed = subscriptionHelper(this.destroyRef);
 * 
 *   ngOnInit() {
 *     this.apiService.getData()
 *       .pipe(this.untilDestroyed())
 *       .subscribe(data => {
 *         // Manejar datos
 *       });
 *   }
 * }
 * ```
 */
export function subscriptionHelper(destroyRef: DestroyRef) {
  const destroy$ = new Subject<void>();
  
  destroyRef.onDestroy(() => {
    destroy$.next();
    destroy$.complete();
  });
  
  return <T>() => takeUntil<T>(destroy$);
}

