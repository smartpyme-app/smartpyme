import { Component, OnInit, TemplateRef, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, ActivatedRoute, Router } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { EmailAccountService, EmailAccount } from '@services/dte-management/email-account.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { TranslatePipe } from '@ngx-translate/core';

const SYNC_COOLDOWN_MS = 10 * 60 * 1000;

@Component({
  selector: 'app-email-accounts',
  templateUrl: './email-accounts.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NgSelectModule, TooltipModule, PopoverModule, TranslatePipe],
})
export class EmailAccountsComponent implements OnInit {
  private readonly countryI18n = inject(CountryI18nService);
  @ViewChild('syncModal') syncModalTpl!: TemplateRef<any>;

  accounts: EmailAccount[] = [];
  loading = false;
  saving = false;
  testing = false;
  savingNotifications = false;
  modalRef!: BsModalRef;
  notificationAccount: EmailAccount | null = null;
  notificationUserId: number | null = null;
  usuarios: any[] = [];

  syncingAccountId: number | null = null;
  syncAllMode = false;
  syncAllTargets: EmailAccount[] = [];
  syncAllFirstTimeCount = 0;
  syncAccountTarget: EmailAccount | null = null;
  syncFechaInicio = '';
  syncFechaFin = '';

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

  get isSyncing(): boolean {
    return this.syncingAccountId !== null;
  }

  hasActiveAccounts(): boolean {
    return this.accounts.some(a => a.is_active);
  }

  private activeAccounts(): EmailAccount[] {
    return this.accounts.filter(a => a.is_active);
  }

  private checkGmailCallbackParams(): void {
    this.route.queryParams.subscribe((params) => {
      const gmail = params['gmail'];
      const email = params['email'];
      const message = params['message'];

      if (gmail === 'success') {
        this.alertService.success('Cuenta Gmail conectada correctamente', email ? `Correo: ${email}` : '');
        this.router.navigate([], { relativeTo: this.route, queryParams: {}, replaceUrl: true });
        this.loadAccounts();
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

  isFirstSync(account: EmailAccount): boolean {
    return !account.last_sync_at;
  }

  canSync(account: EmailAccount): boolean {
    if (!account.is_active || this.isSyncing) {
      return false;
    }
    if (!account.last_sync_at) {
      return true;
    }
    const last = new Date(account.last_sync_at).getTime();
    return Date.now() - last >= SYNC_COOLDOWN_MS;
  }

  syncCooldownLabel(account: EmailAccount): string | null {
    if (!account.last_sync_at || this.canSync(account)) {
      return null;
    }
    const remainingMs = SYNC_COOLDOWN_MS - (Date.now() - new Date(account.last_sync_at).getTime());
    const minutes = Math.ceil(remainingMs / 60000);
    return `Disponible en ${minutes} min`;
  }

  requestSync(account: EmailAccount): void {
    if (!this.canSync(account)) {
      const label = this.syncCooldownLabel(account);
      this.alertService.warning(
        'Espere antes de sincronizar',
        label || 'Puede volver a sincronizar 10 minutos después de la última ejecución.'
      );
      return;
    }

    if (this.isFirstSync(account)) {
      this.syncAllMode = false;
      this.openSyncModal(account, this.syncModalTpl);
      return;
    }

    this.runSync(account, { dias: 30 });
  }

  requestSyncAll(): void {
    const active = this.activeAccounts();
    if (!active.length) {
      this.alertService.warning('Sin cuentas', 'No hay cuentas activas para sincronizar.');
      return;
    }

    const syncable = active.filter(a => this.canSync(a));
    const inCooldown = active.filter(a => !this.canSync(a) && a.last_sync_at);

    if (!syncable.length) {
      this.alertService.warning(
        'Sincronización en espera',
        inCooldown.length
          ? 'Todas las cuentas activas están en periodo de espera (10 minutos entre sincronizaciones).'
          : 'No hay cuentas listas para sincronizar.'
      );
      return;
    }

    const firstTime = syncable.filter(a => this.isFirstSync(a));
    if (firstTime.length) {
      this.syncAllMode = true;
      this.syncAllTargets = syncable;
      this.syncAllFirstTimeCount = firstTime.length;
      this.syncAccountTarget = null;
      this.openSyncModalForAll(this.syncModalTpl);
      return;
    }

    this.runSyncAll(syncable, { dias: 30 });
  }

  openSyncModalForAll(template: TemplateRef<any>): void {
    const today = new Date();
    const past = new Date();
    past.setDate(past.getDate() - 30);
    this.syncFechaFin = this.formatDateInput(today);
    this.syncFechaInicio = this.formatDateInput(past);
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static', keyboard: false });
  }

  openSyncModal(account: EmailAccount, template: TemplateRef<any>): void {
    this.syncAllMode = false;
    this.syncAccountTarget = account;
    const today = new Date();
    const past = new Date();
    past.setDate(past.getDate() - 30);
    this.syncFechaFin = this.formatDateInput(today);
    this.syncFechaInicio = this.formatDateInput(past);
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static', keyboard: false });
  }

  confirmSyncWithRange(): void {
    if (!this.syncFechaInicio || !this.syncFechaFin) {
      this.alertService.warning('Fechas requeridas', 'Seleccione fecha inicial y final.');
      return;
    }
    if (this.syncFechaInicio > this.syncFechaFin) {
      this.alertService.warning('Rango inválido', 'La fecha inicial no puede ser posterior a la final.');
      return;
    }
    this.modalRef?.hide();

    const rangeOptions = {
      fecha_inicio: this.syncFechaInicio,
      fecha_fin: this.syncFechaFin,
    };

    if (this.syncAllMode) {
      this.runSyncAll(this.syncAllTargets, rangeOptions);
      this.syncAllMode = false;
      return;
    }

    if (!this.syncAccountTarget) {
      return;
    }
    this.runSync(this.syncAccountTarget, rangeOptions);
  }

  private formatDateInput(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  private runSync(
    account: EmailAccount,
    options: { dias?: number; fecha_inicio?: string; fecha_fin?: string }
  ): void {
    this.syncingAccountId = account.id;
    this.emailAccountService.sync(account.id, options).subscribe({
      next: (res) => {
        this.syncingAccountId = null;
        this.alertService.success(
          res.message,
          this.buildSyncDetail(res)
        );
        this.loadAccounts();
      },
      error: (err) => {
        this.syncingAccountId = null;
        this.handleSyncError(err);
        this.loadAccounts();
      }
    });
  }

  private runSyncAll(
    accounts: EmailAccount[],
    defaultOptions: { dias?: number; fecha_inicio?: string; fecha_fin?: string }
  ): void {
    const totals = { new: 0, duplicates: 0, failed: 0, synced: 0, skipped: 0 };
    const runAt = (index: number): void => {
      if (index >= accounts.length) {
        this.syncingAccountId = null;
        const parts = [
          `${totals.synced} cuenta(s) sincronizada(s)`,
          `${totals.new} DTE(s) nuevo(s)`,
          `${totals.duplicates} duplicado(s) omitido(s)`,
        ];
        if (totals.failed > 0) {
          parts.push(`${totals.failed} fallido(s)`);
        }
        if (totals.skipped > 0) {
          parts.push(`${totals.skipped} omitida(s) por espera`);
        }
        this.alertService.success('Sincronización completada', parts.join('. ') + '.');
        this.loadAccounts();
        return;
      }

      const account = accounts[index];
      const options = this.isFirstSync(account) && defaultOptions.fecha_inicio
        ? { fecha_inicio: defaultOptions.fecha_inicio, fecha_fin: defaultOptions.fecha_fin }
        : { dias: defaultOptions.dias ?? 30 };

      if (!this.canSync(account)) {
        totals.skipped += 1;
        runAt(index + 1);
        return;
      }

      this.syncingAccountId = account.id;
      this.emailAccountService.sync(account.id, options).subscribe({
        next: (res) => {
          totals.synced += 1;
          totals.new += res.stats?.new ?? 0;
          totals.duplicates += res.stats?.duplicates ?? 0;
          totals.failed += res.stats?.failed ?? 0;
          runAt(index + 1);
        },
        error: () => {
          totals.skipped += 1;
          runAt(index + 1);
        }
      });
    };

    runAt(0);
  }

  private buildSyncDetail(res: {
    date_from?: string;
    date_to?: string;
    stats?: { new: number; duplicates: number; failed: number };
  }): string {
    const range = this.countryI18n.fe('syncSuccessDetail', { from: res.date_from, to: res.date_to });
    const stats = res.stats;
    if (!stats) {
      return range;
    }
    return `${range}. Nuevos: ${stats.new}. Duplicados omitidos: ${stats.duplicates}. Fallidos: ${stats.failed}.`;
  }

  private handleSyncError(err: any): void {
    const retrySec = err?.error?.retry_after_seconds;
    if (err?.status === 429 && retrySec) {
      const minutes = Math.ceil(retrySec / 60);
      this.alertService.warning(
        'Sincronización en espera',
        `Puede volver a intentar en aproximadamente ${minutes} minuto(s).`
      );
    } else {
      this.alertService.error(err);
    }
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
}
