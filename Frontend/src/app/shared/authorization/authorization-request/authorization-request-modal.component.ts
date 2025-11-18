import { Component, OnInit, Input, Output, EventEmitter, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AuthorizationService } from '@services/Authorization/authorization.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-authorization-request-modal',
    templateUrl: './authorization-request-modal.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    styles: [`
    .modal {
      background: rgba(0,0,0,0.5);
      z-index: 1050;
    }
  `],
    
})
export class AuthorizationRequestModalComponent implements OnInit {
  @Input() show: boolean = false;
  @Input() authorizationType: string = '';
  @Input() modelType: string = '';
  @Input() modelId: number | null = null;
  @Input() data: any = {};
  @Output() close = new EventEmitter<void>();
  authorizationRequested = new EventEmitter<{ authorization: any, shouldProceedWithSubmit: boolean }>();

  description: string = '';
  loading: boolean = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private authorizationService: AuthorizationService,
    private alertService: AlertService
  ) { }

  ngOnInit(): void { }

  requestAuthorization() {
    if (!this.description.trim()) {
      this.alertService.error('La descripción es requerida');
      return;
    }

    this.loading = true;

    this.authorizationService.requestAuthorization(
      this.authorizationType,
      this.modelType,
      this.modelId ?? null,
      this.description,
      this.data
    )
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          if (response.ok) {
            if (response.estado === 'Pendiente Autorización') {
              this.alertService.success(
                this.getSuccessTitle(),
                this.getSuccessMessage()
              );
              this.closeModal();
              window.location.href = this.getRedirectUrl();
              return;
            } else {
              this.alertService.success('Autorización solicitada', 'La solicitud fue enviada exitosamente');
              this.closeModal();
            }
          }
          this.loading = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      });
  }

  private getSuccessTitle(): string {
    if (this.authorizationType === 'compras_altas') {
      return 'Compra creada pendiente de autorización';
    } else if (this.authorizationType.startsWith('orden_compra_nivel_')) {
      return 'Orden de compra creada pendiente de autorización';
    } else if (this.authorizationType.startsWith('ventas_')) {
      return 'Venta creada pendiente de autorización';
    }
    return 'Registro creado pendiente de autorización';
  }

  private getSuccessMessage(): string {
    return 'El registro se ha creado y está esperando aprobación.';
  }

  private getRedirectUrl(): string {
    if (this.authorizationType === 'compras_altas') {
      return '/compras';
    } else if (this.authorizationType.startsWith('orden_compra_nivel_')) {
      return '/ordenes-de-compras';
    } else if (this.authorizationType.startsWith('ventas_')) {
      return '/ventas';
    } else if (this.authorizationType.startsWith('editar_usuario_')) {
      return '/usuarios';
    }
    return '/dashboard'; // Por defecto
  }

  // requestAuthorization() {
  //   if (!this.description.trim()) {
  //     this.alertService.error('La descripción es requerida');
  //     return;
  //   }

  //   this.loading = true;

  //   console.log('=== MODAL DEBUG ===');
  //   console.log('authorizationType:', this.authorizationType);
  //   console.log('modelType:', this.modelType);
  //   console.log('data being sent:', this.data);

  //   this.authorizationService.requestAuthorization(
  //     this.authorizationType,
  //     this.modelType,
  //     this.modelId ?? null,
  //     this.description,
  //     this.data
  //   ).subscribe({
  //     next: (response) => {
  //       console.log('Response received:', response);
  //       if (response.ok) {
  //         // Verificar si se creó compra pendiente
  //         if (response.estado === 'Pendiente Autorización' || response.compra) {
  //           this.alertService.success(
  //             'Compra creada pendiente de autorización', 
  //             'La compra se ha creado y está esperando aprobación.'
  //           );
  //           // Redirigir a compras para que vea la compra pendiente
  //           this.closeModal();
  //           window.location.href = '/compras';
  //           return;
  //         } else {
  //           this.alertService.success('Autorización solicitada', 'La solicitud fue enviada exitosamente');
  //           this.closeModal();
  //         }
  //       }
  //       this.loading = false;
  //     },
  //     error: (error) => {
  //       console.error('Error in request:', error);
  //       this.alertService.error(error);
  //       this.loading = false;
  //     }
  //   });
  // }

  closeModal() {
    this.description = '';
    this.loading = false;
    this.close.emit();
  }
}