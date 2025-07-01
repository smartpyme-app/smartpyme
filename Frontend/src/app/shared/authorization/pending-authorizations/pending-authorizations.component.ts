import { Component, OnInit } from '@angular/core';
import { AuthorizationService, Authorization } from '@services/Authorization/authorization.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-pending-authorizations',
  templateUrl: './pending-authorizations.component.html'
})
export class PendingAuthorizationsComponent implements OnInit {
  authorizations: Authorization[] = [];
  loading: boolean = false;
  
  // Modal de aprobación/rechazo
  showApprovalModal: boolean = false;
  selectedAuthorization: Authorization | null = null;
  authorizationCode: string = '';
  notes: string = '';
  processingAction: 'approve' | 'reject' | null = null;

  constructor(
    private authorizationService: AuthorizationService,
    private alertService: AlertService
  ) { }

  ngOnInit(): void {
    this.loadPendingAuthorizations();
  }

  loadPendingAuthorizations() {
    this.loading = true;
    this.authorizationService.getPendingAuthorizations().subscribe({
      next: (response) => {
        if (response.ok) {
          this.authorizations = response.data;
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  openApprovalModal(authorization: Authorization, action: 'approve' | 'reject') {
    this.selectedAuthorization = authorization;
    this.processingAction = action;
    this.showApprovalModal = true;
    this.authorizationCode = '';
    this.notes = '';
  }

  processAuthorization() {
    if (!this.authorizationCode.trim()) {
      this.alertService.error('El código de autorización es requerido');
      return;
    }

    if (!this.selectedAuthorization) return;

    this.loading = true;
    const code = this.selectedAuthorization.code;

    const request = this.processingAction === 'approve' 
      ? this.authorizationService.approveAuthorization(code, this.authorizationCode, this.notes)
      : this.authorizationService.rejectAuthorization(code, this.authorizationCode, this.notes);

    request.subscribe({
      next: (response) => {
        if (response.ok) {
          const action = this.processingAction === 'approve' ? 'aprobada' : 'rechazada';
          this.alertService.success('success',`Autorización ${action} exitosamente`);
          this.loadPendingAuthorizations();
          this.closeApprovalModal();
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    });
  }

  closeApprovalModal() {
    this.showApprovalModal = false;
    this.selectedAuthorization = null;
    this.processingAction = null;
    this.authorizationCode = '';
    this.notes = '';
  }

  getStatusText(status: string): string {
    return this.authorizationService.getStatusText(status);
  }

  getStatusClass(status: string): string {
    return this.authorizationService.getStatusClass(status);
  }
}
