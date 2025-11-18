import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { AuthorizationService } from '@services/Authorization/authorization.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-authorization-view',
    templateUrl: './authorization-view.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class AuthorizationViewComponent implements OnInit {
  authorization: any = null;
  loading = false;
  authCode: string = '';
  notes: string = '';
  processing = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private route: ActivatedRoute,
    private authorizationService: AuthorizationService,
    private alertService: AlertService
  ) { }

  ngOnInit() {
    const code = this.route.snapshot.paramMap.get('code');
    if (code) {
      this.loadAuthorization(code);
    }
  }

  loadAuthorization(code: string) {
    this.loading = true;
    this.authorizationService.getAuthorization(code)
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          this.authorization = response.data;
          this.loading = false;
        },
        error: () => {
          this.loading = false;
        }
      });
  }

  approve() {
    this.processing = true;
    this.authorizationService.approveAuthorization(
      this.authorization.code, 
      this.authCode, 
      this.notes
    )
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          if (response.ok) {
            this.alertService.success("Autorización aprobada exitosamente","Autorización aprobada exitosamente");
            this.loadAuthorization(this.authorization.code); // Recargar
          }
          this.processing = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.processing = false;
        }
      });
  }

  reject() {
    this.processing = true;
    this.authorizationService.rejectAuthorization(
      this.authorization.code, 
      this.authCode, 
      this.notes
    )
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (response) => {
          if (response.ok) {
            this.alertService.success("Autorización rechazada","Autorización rechazada");
            this.loadAuthorization(this.authorization.code); // Recargar
          }
          this.processing = false;
        },
        error: (error) => {
          this.alertService.error(error);
          this.processing = false;
        }
      });
  }

}