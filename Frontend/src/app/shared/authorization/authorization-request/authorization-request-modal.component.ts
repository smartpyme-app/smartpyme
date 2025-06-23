import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { AuthorizationService } from '@services/Authorization/authorization.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-authorization-request-modal',
  templateUrl: './authorization-request-modal.component.html',
  styles: [`
    .modal {
      background: rgba(0,0,0,0.5);
      z-index: 1050;
    }
  `]
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

    console.log('=== MODAL DEBUG ===');
    console.log('authorizationType:', this.authorizationType);
    console.log('modelType:', this.modelType);
    console.log('data being sent:', this.data);

    this.authorizationService.requestAuthorization(
      this.authorizationType,
      this.modelType,
      this.modelId ?? null,
      this.description,
      this.data
    ).subscribe({
      next: (response) => {
        console.log('Response received:', response);
        if (response.ok) {
          // Verificar si se creó compra pendiente
          if (response.estado === 'Pendiente Autorización' || response.compra) {
            this.alertService.success(
              'Compra creada pendiente de autorización', 
              'La compra se ha creado y está esperando aprobación.'
            );
            // Redirigir a compras para que vea la compra pendiente
            this.closeModal();
            window.location.href = '/compras';
            return;
          } else {
            this.alertService.success('Autorización solicitada', 'La solicitud fue enviada exitosamente');
            this.closeModal();
          }
        }
        this.loading = false;
      },
      error: (error) => {
        console.error('Error in request:', error);
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  closeModal() {
    this.description = '';
    this.loading = false;
    this.close.emit();
  }
}