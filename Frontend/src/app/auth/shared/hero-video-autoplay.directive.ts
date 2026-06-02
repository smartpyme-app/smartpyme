import { AfterViewInit, Directive, ElementRef, OnDestroy } from '@angular/core';

@Directive({
  selector: 'video[appHeroVideoAutoplay]',
})
export class HeroVideoAutoplayDirective implements AfterViewInit, OnDestroy {
  private readonly onMediaReady = () => this.tryPlay();
  private readonly onPageshow = () => this.tryPlay();

  constructor(private readonly el: ElementRef<HTMLVideoElement>) {}

  ngAfterViewInit(): void {
    const video = this.el.nativeElement;
    video.muted = true;
    video.defaultMuted = true;
    video.playsInline = true;
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');

    video.addEventListener('loadeddata', this.onMediaReady);
    video.addEventListener('canplay', this.onMediaReady);
    window.addEventListener('pageshow', this.onPageshow);

    requestAnimationFrame(() => this.tryPlay());
    setTimeout(() => this.tryPlay(), 150);
  }

  ngOnDestroy(): void {
    const video = this.el.nativeElement;
    video.removeEventListener('loadeddata', this.onMediaReady);
    video.removeEventListener('canplay', this.onMediaReady);
    window.removeEventListener('pageshow', this.onPageshow);
  }

  private tryPlay(): void {
    const video = this.el.nativeElement;
    video.muted = true;

    if (video.paused) {
      void video.play().catch(() => {});
    }
  }
}
