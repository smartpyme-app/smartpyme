import {
  Component,
  OnDestroy,
  OnInit,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  DestroyRef,
  inject,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

export interface HomeBannerSlide {
  id: number;
  nombre: string;
  imagen_desktop: string;
  imagen_mobile: string | null;
  url_destino: string | null;
  abrir_nueva_pestana: boolean;
  segundos_visualizacion: number;
}

@Component({
  selector: 'app-home-marketing-banners',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './home-marketing-banners.component.html',
  styleUrls: ['./home-marketing-banners.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomeMarketingBannersComponent implements OnInit, OnDestroy {
  private readonly destroyRef = inject(DestroyRef);
  private readonly untilDestroyed = subscriptionHelper(this.destroyRef);

  public banners: HomeBannerSlide[] = [];
  public currentIndex = 0;
  private rotationTimer: ReturnType<typeof setInterval> | null = null;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private cdr: ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    this.apiService
      .getAll('home-banners', {})
      .pipe(this.untilDestroyed())
      .subscribe({
        next: (rows) => {
          this.banners = Array.isArray(rows) ? rows : [];
          this.currentIndex = 0;
          this.scheduleRotation();
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.alertService.error(err);
          this.cdr.markForCheck();
        },
      });
  }

  ngOnDestroy(): void {
    this.clearRotation();
  }

  public imgUrl(path: string | null | undefined): string {
    if (!path) {
      return '';
    }
    const normalized = path.startsWith('/') ? path.slice(1) : path;
    return `${this.apiService.baseUrl}/img/${normalized}`;
  }

  public mobileSrc(banner: HomeBannerSlide): string {
    return this.imgUrl(banner.imagen_mobile || banner.imagen_desktop);
  }

  public goTo(index: number): void {
    if (index < 0 || index >= this.banners.length) {
      return;
    }
    this.currentIndex = index;
    this.scheduleRotation();
    this.cdr.markForCheck();
  }

  private scheduleRotation(): void {
    this.clearRotation();
    if (this.banners.length <= 1) {
      return;
    }
    const seconds = this.banners[this.currentIndex]?.segundos_visualizacion ?? 5;
    const ms = Math.max(2000, seconds * 1000);
    this.rotationTimer = setInterval(() => {
      this.currentIndex = (this.currentIndex + 1) % this.banners.length;
      this.scheduleRotation();
      this.cdr.markForCheck();
    }, ms);
  }

  private clearRotation(): void {
    if (this.rotationTimer !== null) {
      clearInterval(this.rotationTimer);
      this.rotationTimer = null;
    }
  }
}