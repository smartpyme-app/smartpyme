import { Component, OnInit, Input } from '@angular/core';
import { AuthorizationService, Authorization } from '@services/Authorization/authorization.service';

@Component({
  selector: 'app-authorization-history',
  templateUrl: './authorization-history.component.html'
})
export class AuthorizationHistoryComponent implements OnInit {
  @Input() modelType: string = '';
  @Input() modelId: number = 0;

  history: Authorization[] = [];
  loading: boolean = false;

  constructor(private authorizationService: AuthorizationService) { }

  ngOnInit(): void {
    if (this.modelType && this.modelId) {
      this.loadHistory();
    }
  }

  loadHistory() {
    this.loading = true;
    this.authorizationService.getAuthorizationHistory(this.modelType, this.modelId)
      .subscribe({
        next: (response) => {
          if (response.ok) {
            this.history = response.data;
          }
          this.loading = false;
        },
        error: () => {
          this.loading = false;
        }
      });
  }

  getStatusText(status: string): string {
    return this.authorizationService.getStatusText(status);
  }

  getStatusClass(status: string): string {
    return this.authorizationService.getStatusClass(status);
  }
}