import { Component, OnInit, OnDestroy, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { interval } from 'rxjs';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-whatsapp-estadisticas',
    templateUrl: './whatsapp-estadisticas.component.html',
    styleUrls: ['./whatsapp-estadisticas.component.scss'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class WhatsAppEstadisticasComponent implements OnInit, OnDestroy {

  public stats: any = null;
  public executiveSummary: any = null;
  public loading: boolean = false;
  public error: string = '';
  public selectedPeriod: number = 30;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);
  public autoRefreshEnabled: boolean = true;
  public refreshInterval: number = 60;

  constructor(
    public apiService: ApiService,
    public alertService: AlertService
  ) {}

  ngOnInit() {
    this.loadInitialData();
    this.setupAutoRefresh();
  }

  ngOnDestroy() {
    // El DestroyRef maneja automáticamente la limpieza
  }

  loadInitialData() {
    this.loading = true;
    console.log('📊 Cargando estadísticas iniciales...');
    
    Promise.all([
      this.loadStats(),
      this.loadExecutiveSummary()
    ]).finally(() => {
      this.loading = false;
      console.log('✅ Estadísticas cargadas');
    });
  }

  loadStats(): Promise<void> {
    return new Promise((resolve) => {
      console.log('📈 Cargando estadísticas WhatsApp...');
      
      const params = {
        days: this.selectedPeriod
      };

      this.apiService.getAll('admin/whatsapp/stats', params)
        .pipe(this.untilDestroyed())
        .subscribe(
        (response) => {
          if (response && response.success) {
            this.stats = response.data;
            this.error = '';
          } else {
            this.stats = response;
            this.error = '';
          }
          resolve();
        },
        (error) => {
          console.error('Error cargando estadísticas:', error);
          this.error = 'Error al cargar las estadísticas. Por favor, intenta de nuevo.';
          this.stats = null;
          resolve();
        }
      );
    });
  }

  loadExecutiveSummary(): Promise<void> {
    return new Promise((resolve) => {
      console.log('📋 Cargando resumen ejecutivo...');
      
      const params = {
        days: this.selectedPeriod
      };

      this.apiService.getAll('admin/whatsapp/executive-summary', params)
        .pipe(this.untilDestroyed())
        .subscribe(
        (response) => {
          if (response && response.success) {
            this.executiveSummary = response.data;
          } else {
            this.executiveSummary = response;
          }
          resolve();
        },
        (error) => {
          console.error('Error cargando resumen ejecutivo:', error);
          this.executiveSummary = null;
          resolve();
        }
      );
    });
  }

  setupAutoRefresh() {
    if (this.autoRefreshEnabled) {
      interval(this.refreshInterval * 1000)
        .pipe(this.untilDestroyed())
        .subscribe(() => {
        this.refreshData();
      });
    }
  }

  refreshData() {
    console.log('🔄 Refrescando estadísticas...');
    Promise.all([
      this.loadStats(),
      this.loadExecutiveSummary()
    ]).then(() => {
      console.log('✅ Estadísticas actualizadas');
    });
  }

  
  getConnectionPercentage(): number {
    if (!this.stats?.sessions?.total || this.stats.sessions.total === 0) return 0;
    return Math.round((this.stats.sessions.connected / this.stats.sessions.total) * 100);
  }

  getIncomingPercentage(): number {
    if (!this.stats?.messages?.total || this.stats.messages.total === 0) return 0;
    return Math.round((this.stats.messages.incoming / this.stats.messages.total) * 100);
  }

  getOutgoingPercentage(): number {
    if (!this.stats?.messages?.total || this.stats.messages.total === 0) return 0;
    return Math.round((this.stats.messages.outgoing / this.stats.messages.total) * 100);
  }

  getCompanyActivityPercentage(sessionCount: number): number {
    if (!this.stats?.top_companies?.length) return 0;
    
    const maxSessions = Math.max(...this.stats.top_companies.map((c: any) => c.session_count));
    if (maxSessions === 0) return 0;
    
    return Math.round((sessionCount / maxSessions) * 100);
  }

  formatDate(dateString: string): string {
    try {
      const date = new Date(dateString);
      const today = new Date();
      const yesterday = new Date(today);
      yesterday.setDate(yesterday.getDate() - 1);

      if (date.toDateString() === today.toDateString()) {
        return 'Hoy';
      } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Ayer';
      } else {
        return date.toLocaleDateString('es-ES', { 
          day: '2-digit', 
          month: '2-digit' 
        });
      }
    } catch (error) {
      return dateString;
    }
  }

  formatHour(hour: number): string {
    if (hour === undefined || hour === null) return '--:--';
    
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
    
    return `${displayHour}:00 ${period}`;
  }

  getGrowthIcon(growth: number): string {
    return growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
  }

  getGrowthClass(growth: number): string {
    return growth >= 0 ? 'text-success' : 'text-danger';
  }

  getGrowthBadgeClass(growth: number): string {
    return growth >= 0 ? 'bg-success' : 'bg-danger';
  }

  exportStats() {
    this.loading = true;
    console.log('📥 Exportando estadísticas...');
    
    const params = {
      days: this.selectedPeriod,
      format: 'excel'
    };

    this.apiService.getAll('admin/whatsapp/stats/export', params)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response) => {
    
        this.alertService.success('Estadísticas exportadas correctamente', 'WhatsApp');
        this.loading = false;
      },
      (error) => {
        this.alertService.error('Error al exportar estadísticas');
        this.loading = false;
      }
    );
  }

  onPeriodChange() {
    console.log('📅 Cambiando período a:', this.selectedPeriod, 'días');
    this.loadInitialData();
  }

  getPeriodLabel(): string {
    switch (this.selectedPeriod) {
      case 7: return 'Última semana';
      case 30: return 'Último mes';
      case 90: return 'Últimos 3 meses';
      default: return `Últimos ${this.selectedPeriod} días`;
    }
  }
}