import { Injectable, NgZone, OnDestroy } from '@angular/core';
import { Subject, Subscription } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { environment } from '../../environments/environment';
import { ApiService } from '@services/api.service';

export type RestauranteRealtimeHint = 'mapa' | 'cocina';

/**
 * Capa opcional de UI. Si realtime está deshabilitado o WS falla, no-op:
 * el mapa/cocina siguen con GET manual / post-mutación.
 */
@Injectable({ providedIn: 'root' })
export class RestauranteRealtimeService implements OnDestroy {
  private echo: Echo<'reverb'> | null = null;
  private channelName: string | null = null;
  private mapa$ = new Subject<void>();
  private cocina$ = new Subject<void>();
  private subs: Subscription[] = [];
  private connected = false;
  private readonly seenEventKeys = new Set<string>();

  constructor(
    private api: ApiService,
    private zone: NgZone
  ) {
    (window as any).Pusher = Pusher;
  }

  get isEnabled(): boolean {
    return !!(environment as any).restauranteRealtime?.enabled;
  }

  /**
   * Suscribe hints. El callback debe hacer GET/refresh (SoT HTTP).
   * Tolera duplicados (debounce + dedupe de event id corto).
   */
  watch(hint: RestauranteRealtimeHint, onHint: () => void): Subscription {
    this.ensureConnected();
    const stream = hint === 'mapa' ? this.mapa$ : this.cocina$;
    const sub = stream.pipe(debounceTime(400)).subscribe(() => {
      this.zone.run(() => onHint());
    });
    this.subs.push(sub);
    return sub;
  }

  /** Tras reconectar / volver visible: el caller hace GET. */
  onRecover(cb: () => void): void {
    const handler = () => {
      if (document.visibilityState === 'visible') {
        this.zone.run(() => cb());
      }
    };
    const online = () => this.zone.run(() => {
      this.ensureConnected(true);
      cb();
    });
    document.addEventListener('visibilitychange', handler);
    window.addEventListener('online', online);
    this.subs.push(new Subscription(() => {
      document.removeEventListener('visibilitychange', handler);
      window.removeEventListener('online', online);
    }));
  }

  ngOnDestroy(): void {
    this.disconnect();
    this.subs.forEach((s) => s.unsubscribe());
    this.subs = [];
  }

  private ensureConnected(force = false): void {
    if (!this.isEnabled) {
      return;
    }
    if (this.connected && !force) {
      return;
    }

    const cfg = (environment as any).restauranteRealtime || {};
    const token = this.api.auth_token();
    const user = this.api.auth_user();
    const empresaId = user?.id_empresa ?? user?.empresa?.id;
    if (!token || !empresaId || !cfg.key) {
      return;
    }

    try {
      this.disconnect();
      this.echo = new Echo({
        broadcaster: 'reverb',
        key: cfg.key,
        wsHost: cfg.wsHost || window.location.hostname,
        wsPort: cfg.wsPort ?? 8080,
        wssPort: cfg.wssPort ?? 443,
        forceTLS: !!cfg.forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${environment.API_URL}/api/broadcasting/auth`,
        auth: {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        },
      });

      this.channelName = `restaurante.empresa.${empresaId}`;
      const channel = this.echo.private(this.channelName);
      channel.listen('.mapa.updated', (payload: any) => this.onEvent('mapa', payload));
      channel.listen('.cocina.updated', (payload: any) => this.onEvent('cocina', payload));

      this.echo.connector.pusher.connection.bind('connected', () => {
        this.connected = true;
      });
      this.echo.connector.pusher.connection.bind('disconnected', () => {
        this.connected = false;
      });
      this.echo.connector.pusher.connection.bind('unavailable', () => {
        this.connected = false;
      });
    } catch {
      this.connected = false;
      this.echo = null;
    }
  }

  private onEvent(kind: RestauranteRealtimeHint, payload: any): void {
    const key = `${kind}:${payload?.mesa_id ?? ''}:${payload?.comanda_id ?? ''}:${payload?.sesion_id ?? ''}:${payload?.estado ?? ''}:${payload?.reason ?? ''}:${payload?.ts ?? Date.now()}`;
    // Dedup corto: mismo payload repetido en <2s se ignora (FE tolera dupes vía debounce también).
    if (this.seenEventKeys.has(key)) {
      return;
    }
    this.seenEventKeys.add(key);
    setTimeout(() => this.seenEventKeys.delete(key), 2000);

    if (kind === 'mapa') {
      this.mapa$.next();
    } else {
      this.cocina$.next();
    }
  }

  private disconnect(): void {
    if (this.echo && this.channelName) {
      try {
        this.echo.leave(this.channelName);
      } catch {
        /* ignore */
      }
    }
    if (this.echo) {
      try {
        this.echo.disconnect();
      } catch {
        /* ignore */
      }
    }
    this.echo = null;
    this.channelName = null;
    this.connected = false;
  }
}
