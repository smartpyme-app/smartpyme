import { Component, OnInit, TemplateRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { EmailAccountService, EmailAccount } from '@services/dte-management/email-account.service';

@Component({
  selector: 'app-email-accounts',
  templateUrl: './email-accounts.component.html'
})
export class EmailAccountsComponent implements OnInit {
  accounts: EmailAccount[] = [];
  loading = false;
  saving = false;
  testing = false;
  savingNotifications = false;
  modalRef!: BsModalRef;
  notificationAccount: EmailAccount | null = null;
  notificationUserId: number | null = null;
  usuarios: any[] = [];

  imapConfig = {
    host: '',
    port: 993,
    encryption: 'ssl',
    user: '',
    password: '',
    id_sucursal: null as number | null,
    id_bodega: null as number | null,
    actualizar_inventario: false
  };

  sucursales: any[] = [];
  bodegas: any[] = [];

  constructor(
    private emailAccountService: EmailAccountService,
    private apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.loadAccounts();
    this.loadSucursales();
    this.loadBodegas();
    this.loadUsuarios();
    this.checkGmailCallbackParams();
  }

  private checkGmailCallbackParams(): void {
    this.route.queryParams.subscribe((params) => {
      const gmail = params['gmail'];
      const email = params['email'];
      const message = params['message'];

      if (gmail === 'success') {
        this.alertService.success('Cuenta Gmail conectada correctamente', email ? `Correo: ${email}` : '');
        this.router.navigate([], { relativeTo: this.route, queryParams: {}, replaceUrl: true });
      } else if (gmail === 'error') {
        this.alertService.warning('Error al conectar Gmail', message || 'Intente de nuevo.');
        this.router.navigate([], { relativeTo: this.route, queryParams: {}, replaceUrl: true });
      }
    });
  }

  loadAccounts(): void {
    this.loading = true;
    this.emailAccountService.list().subscribe({
      next: (accounts) => {
        this.accounts = accounts;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      }
    });
  }

  loadSucursales(): void {
    this.apiService.getAll('sucursales/list').subscribe({
      next: (data) => this.sucursales = data,
      error: (err) => this.alertService.error(err)
    });
  }

  loadBodegas(): void {
    this.apiService.getAll('bodegas/list').subscribe({
      next: (data) => this.bodegas = data,
      error: (err) => this.alertService.error(err)
    });
  }

  loadUsuarios(): void {
    this.apiService.getAll('usuarios/list').subscribe({
      next: (data) => this.usuarios = data,
      error: (err) => this.alertService.error(err)
    });
  }

  connectGmail(): void {
    this.emailAccountService.getGmailAuthUrl().subscribe({
      next: (res) => { window.location.href = res.url; },
      error: (err) => this.alertService.error(err)
    });
  }

  openImapModal(template: TemplateRef<any>): void {
    this.imapConfig = {
      host: '',
      port: 993,
      encryption: 'ssl',
      user: '',
      password: '',
      id_sucursal: null,
      id_bodega: null,
      actualizar_inventario: false
    };
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  openNotificationModal(template: TemplateRef<any>, account: EmailAccount): void {
    this.notificationAccount = account;
    this.notificationUserId = account.notification_user_id ?? null;
    this.modalRef = this.modalService.show(template, { class: 'modal-md' });
  }

  saveNotificationConfig(): void {
    if (!this.notificationAccount) {
      return;
    }

    this.savingNotifications = true;
    this.emailAccountService.updateNotificaciones(this.notificationAccount.id, this.notificationUserId).subscribe({
      next: () => {
        this.savingNotifications = false;
        this.modalRef?.hide();
        this.alertService.success('Configuración guardada', 'Usuario de notificaciones actualizado.');
        this.loadAccounts();
      },
      error: (err) => {
        this.savingNotifications = false;
        this.alertService.error(err);
      }
    });
  }

  testImapConnection(): void {
    this.testing = true;
    this.emailAccountService.testImap({
      host: this.imapConfig.host,
      port: this.imapConfig.port,
      encryption: this.imapConfig.encryption,
      user: this.imapConfig.user,
      password: this.imapConfig.password
    }).subscribe({
      next: (res) => {
        this.testing = false;
        this.alertService.success(res.message, res.success ? 'Conexión exitosa' : '');
        if (!res.success) {
          this.alertService.warning('No se pudo conectar', res.message);
        }
      },
      error: (err) => {
        this.testing = false;
        this.alertService.error(err);
      }
    });
  }

  saveImapAccount(): void {
    const user = this.apiService.auth_user();
    if (!user) {
      this.alertService.error({ error: { message: 'No autenticado' } });
      return;
    }

    this.saving = true;
    this.emailAccountService.storeImap({
      ...this.imapConfig,
      id_sucursal: this.imapConfig.id_sucursal,
      id_bodega: this.imapConfig.id_bodega,
      actualizar_inventario: this.imapConfig.actualizar_inventario
    }).subscribe({
      next: () => {
        this.saving = false;
        this.modalRef?.hide();
        this.alertService.success('Cuenta conectada', 'La cuenta IMAP se conectó correctamente.');
        this.loadAccounts();
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      }
    });
  }

  syncAccount(account: EmailAccount): void {
    this.saving = true;
    this.emailAccountService.sync(account.id).subscribe({
      next: (res) => {
        this.saving = false;
        this.alertService.success(res.message, `Sincronización iniciada (${res.date_from} - ${res.date_to})`);
        this.loadAccounts();
      },
      error: (err) => {
        this.saving = false;
        this.alertService.error(err);
      }
    });
  }

  disconnectAccount(account: EmailAccount): void {
    if (!confirm(`¿Desconectar la cuenta ${account.email}?`)) return;

    this.emailAccountService.destroy(account.id).subscribe({
      next: () => {
        this.alertService.success('Cuenta desconectada', '');
        this.loadAccounts();
      },
      error: (err) => this.alertService.error(err)
    });
  }

  providerLabel(provider: string): string {
    return provider === 'gmail' ? 'Gmail' : provider === 'imap' ? 'IMAP' : provider;
  }

  notificationUserLabel(account: EmailAccount): string {
    return account.notification_user?.name ?? 'Sin configurar';
  }
}
