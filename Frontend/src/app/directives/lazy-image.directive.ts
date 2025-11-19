import { Directive, ElementRef, Input, OnChanges, SimpleChanges, OnDestroy, AfterViewInit, PLATFORM_ID, inject } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';

@Directive({
  selector: 'img[appLazyImage]',
  standalone: true
})
export class LazyImageDirective implements AfterViewInit, OnChanges, OnDestroy {

  @Input() appLazyImage: string = '';
  @Input() fallback?: string; // Imagen de respaldo si falla la carga

  private observer?: IntersectionObserver;
  private imageLoaded = false;
  private readonly platformId = inject(PLATFORM_ID);
  private readonly isBrowser = isPlatformBrowser(this.platformId);

  constructor(private el: ElementRef<HTMLImageElement>) {}

  ngAfterViewInit(): void {
    if (!this.isBrowser) {
      // En SSR, cargar inmediatamente
      if (this.appLazyImage) {
        this.loadImage();
      }
      return;
    }

    // Si ya hay una URL, intentar cargar
    if (this.appLazyImage) {
      this.initializeImage();
    } else {
      // Si no hay URL aún, esperar a que se asigne
      this.setupObserver();
    }
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['appLazyImage'] && !changes['appLazyImage'].firstChange && this.isBrowser) {
      // Si cambia la URL y ya había una imagen cargada, resetear
      if (this.observer) {
        this.observer.disconnect();
        this.observer = undefined;
      }
      this.imageLoaded = false;
      this.initializeImage();
    }
  }

  private initializeImage(): void {
    if (!this.appLazyImage || this.imageLoaded) {
      return;
    }

    const img = this.el.nativeElement;

    // Verificar si la imagen ya está visible en el viewport
    if (this.isElementInViewport(img)) {
      this.loadImage();
    } else {
      // Usar IntersectionObserver para lazy loading
      this.setupObserver();
    }
  }

  private isElementInViewport(element: HTMLElement): boolean {
    const rect = element.getBoundingClientRect();
    return (
      rect.top >= 0 &&
      rect.left >= 0 &&
      rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
      rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
  }

  private setupObserver(): void {
    if (!this.isBrowser || !this.appLazyImage || this.imageLoaded) {
      return;
    }

    // Verificar si IntersectionObserver está disponible
    if (typeof IntersectionObserver === 'undefined') {
      // Fallback: cargar inmediatamente si no hay soporte
      this.loadImage();
      return;
    }

    // Limpiar observer anterior si existe
    if (this.observer) {
      this.observer.disconnect();
    }

    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && this.appLazyImage && !this.imageLoaded) {
            this.loadImage();
            this.disconnectObserver();
          }
        });
      },
      {
        rootMargin: '50px', // Cargar 50px antes de que sea visible
        threshold: 0.01
      }
    );

    this.observer.observe(this.el.nativeElement);
  }

  private loadImage(): void {
    if (!this.appLazyImage || this.imageLoaded) {
      return;
    }

    const img = this.el.nativeElement;

    // Marcar como cargando para evitar cargas duplicadas
    this.imageLoaded = true;

    // Configurar manejadores de eventos
    img.onload = () => {
      img.classList.add('lazy-loaded');
    };

    img.onerror = () => {
      // Si hay una imagen de respaldo, intentar cargarla
      if (this.fallback) {
        img.src = this.fallback;
      } else {
        // Agregar clase para estilos de error si es necesario
        img.classList.add('lazy-error');
      }
    };

    // Asignar la URL de la imagen
    img.src = this.appLazyImage;

    // Si la imagen ya está en caché, el evento onload puede no dispararse
    if (img.complete) {
      img.classList.add('lazy-loaded');
    }
  }

  private disconnectObserver(): void {
    if (this.observer) {
      this.observer.disconnect();
      this.observer = undefined;
    }
  }

  ngOnDestroy(): void {
    this.disconnectObserver();
  }
}

