import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { SyncLogService, SyncLog, SyncLogsResponse } from '@services/dte-management/sync-log.service';
import { EmailAccountService } from '@services/dte-management/email-account.service';

@Component({
  selector: 'app-sync-dashboard',
  templateUrl: './sync-dashboard.component.html'
})
export class SyncDashboardComponent implements OnInit {
  logs: SyncLogsResponse | null = null;
  accounts: any[] = [];
  loading = false;
  filtros: any = {};
  logErroresAbierto: number | null = null;

  constructor(
    private syncLogService: SyncLogService,
    private emailAccountService: EmailAccountService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.route.queryParams.subscribe((params) => {
      const id = params['user_email_account_id'];
      this.filtros = {
        user_email_account_id: id && id !== '' ? +id : '',
        status: params['status'] || '',
        page: +(params['page'] || 1),
        per_page: +(params['per_page'] || 15)
      };
      this.loadLogs();
    });
    this.emailAccountService.list().subscribe({
      next: (acc) => (this.accounts = acc),
      error: (err) => this.alertService.error(err)
    });
  }

  loadLogs(): void {
    this.loading = true;
    const params: any = { page: this.filtros.page, per_page: this.filtros.per_page };
    if (this.filtros.user_email_account_id) params.user_email_account_id = this.filtros.user_email_account_id;
    if (this.filtros.status) params.status = this.filtros.status;

    this.syncLogService.list(params).subscribe({
      next: (data) => {
        this.logs = data;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      }
    });
  }

  setPagination(event: any): void {
    this.filtros.page = event.page;
    this.router.navigate([], { relativeTo: this.route, queryParams: this.filtros, queryParamsHandling: 'merge' });
  }

  filtrar(): void {
    this.filtros.page = 1;
    this.router.navigate([], { relativeTo: this.route, queryParams: this.filtros, queryParamsHandling: 'merge' });
  }

  statusBadgeClass(status: string): string {
    switch (status) {
      case 'completed': return 'bg-success';
      case 'failed': return 'bg-danger';
      case 'running': return 'bg-primary';
      default: return 'bg-secondary';
    }
  }

  toggleErrores(logId: number): void {
    this.logErroresAbierto = this.logErroresAbierto === logId ? null : logId;
  }
}
