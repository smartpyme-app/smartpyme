import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { AuthorizationService } from '@services/Authorization/authorization.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-authorization-view',
  templateUrl: './authorization-view.component.html'
})
export class AuthorizationViewComponent implements OnInit {
  authorization: any = null;
  loading = false;
  authCode: string = '';
  notes: string = '';
  processing = false;

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
    this.authorizationService.getAuthorization(code).subscribe({
      next: (response) => {
        this.authorization = response.data;
        this.loading = false;
      },
      error: () => this.loading = false
    });
  }

  approve() {
    this.processing = true;
    this.authorizationService.approveAuthorization(
      this.authorization.code, 
      this.authCode, 
      this.notes
    ).subscribe({
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
    ).subscribe({
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