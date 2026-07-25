import {
  Component,
  Input,
  HostListener,
  ElementRef,
  ViewChild,
  ContentChildren,
  QueryList,
  OnDestroy,
  AfterViewInit,
  ChangeDetectorRef,
  Renderer2,
} from '@angular/core';
import { AgGridAngular } from 'ag-grid-angular';
import * as echarts from 'echarts';

@Component({
  selector: 'app-chart-expandable',
  templateUrl: './chart-expandable.component.html',
  styleUrls: ['./chart-expandable.component.css'],
})
export class ChartExpandableComponent implements AfterViewInit, OnDestroy {
  @Input() title = '';

  @ViewChild('panel') panelRef!: ElementRef<HTMLElement>;
  /** Grids proyectados (tablas dentro del expandable) */
  @ContentChildren(AgGridAngular, { descendants: true })
  grids!: QueryList<AgGridAngular>;

  /** Modal montado (fixed + backdrop) */
  expanded = false;
  /** scale del panel vía transition (opacity del panel siempre 1) */
  panelOpen = false;
  placeholderHeight: number | null = null;

  private closeTimer: ReturnType<typeof setTimeout> | null = null;
  private fitTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly animMs = 220;
  private cardEl: HTMLElement | null = null;
  private cardHadPosition = false;

  constructor(
    private cdr: ChangeDetectorRef,
    private host: ElementRef<HTMLElement>,
    private renderer: Renderer2,
  ) {}

  ngAfterViewInit(): void {
    this.cardEl = this.host.nativeElement.closest('.card');
    if (!this.cardEl) {
      return;
    }
    const computed = getComputedStyle(this.cardEl).position;
    this.cardHadPosition = computed !== 'static';
    if (!this.cardHadPosition) {
      this.renderer.setStyle(this.cardEl, 'position', 'relative');
    }
  }

  onToggleClick(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    if (this.expanded) {
      this.close();
    } else {
      this.open();
    }
  }

  open(): void {
    if (this.expanded) {
      return;
    }
    this.clearCloseTimer();
    this.clearFitTimer();
    this.panelOpen = false;

    const panel = this.panelRef?.nativeElement;
    if (panel) {
      this.placeholderHeight = panel.offsetHeight;
    }

    // Modal visible (opacity 1) en scale 0.96 → resize → anima a scale 1.
    // Nunca opacity 0: echarts queda en blanco si hace resize oculto.
    this.expanded = true;
    this.panelOpen = false;
    document.body.style.overflow = 'hidden';
    this.cdr.detectChanges();

    requestAnimationFrame(() => {
      this.resizeChartsInPanel();
      requestAnimationFrame(() => {
        this.panelOpen = true;
        this.cdr.detectChanges();
        // Tras el scale a ancho completo: llenar columnas (evita hueco blanco)
        this.scheduleAgGridFit(this.animMs);
      });
    });
  }

  close(): void {
    if (!this.expanded) {
      return;
    }
    if (!this.panelOpen) {
      this.finishClose();
      return;
    }

    this.panelOpen = false;
    this.cdr.detectChanges();
    this.clearCloseTimer();
    this.closeTimer = setTimeout(() => this.finishClose(), this.animMs);
  }

  @HostListener('document:keydown.escape', ['$event'])
  onEscape(event: Event): void {
    if (!this.expanded) {
      return;
    }
    event.stopPropagation();
    this.close();
  }

  ngOnDestroy(): void {
    this.clearCloseTimer();
    this.clearFitTimer();
    if (this.expanded) {
      document.body.style.overflow = '';
    }
    if (this.cardEl && !this.cardHadPosition) {
      this.renderer.removeStyle(this.cardEl, 'position');
    }
  }

  private finishClose(): void {
    this.clearCloseTimer();
    this.expanded = false;
    this.panelOpen = false;
    this.placeholderHeight = null;
    document.body.style.overflow = '';
    this.cdr.detectChanges();

    // Resize ya en el card + reajustar columnas al ancho reducido
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        this.resizeChartsInPanel();
        this.fitAgGridColumns();
      });
    });
  }

  private clearCloseTimer(): void {
    if (this.closeTimer) {
      clearTimeout(this.closeTimer);
      this.closeTimer = null;
    }
  }

  private clearFitTimer(): void {
    if (this.fitTimer) {
      clearTimeout(this.fitTimer);
      this.fitTimer = null;
    }
  }

  private scheduleAgGridFit(delayMs: number): void {
    this.clearFitTimer();
    this.fitTimer = setTimeout(() => {
      this.fitTimer = null;
      this.fitAgGridColumns();
    }, delayMs);
  }

  /** api.sizeColumnsToFit() — API nativa de ag-grid para llenar el ancho */
  private fitAgGridColumns(): void {
    this.grids?.forEach((grid) => {
      try {
        grid.api?.sizeColumnsToFit();
      } catch {
        /* grid aún no listo */
      }
    });
  }

  private resizeChartsInPanel(): void {
    const panel = this.panelRef?.nativeElement;
    if (!panel) {
      return;
    }
    panel.querySelectorAll<HTMLElement>('div').forEach((el) => {
      const instance = echarts.getInstanceByDom(el);
      if (instance) {
        instance.resize();
      }
    });
    window.dispatchEvent(new Event('resize'));
    this.fitAgGridColumns();
  }
}
