import { Injectable } from '@angular/core';
import { Observable, EMPTY, from, throwError } from 'rxjs';
import { catchError, switchMap } from 'rxjs/operators';
import Swal from 'sweetalert2';
import { ApiService } from '@services/api.service';

export type DetalleSinProductoPayload = {
  code: 'detalle_sin_producto';
  titulo?: string;
  error?: string;
  id_venta?: number;
  detalle: { id: number; descripcion?: string; id_producto?: number };
  sugerencias?: Array<{ id: number; nombre: string; codigo?: string | null; tipo?: string }>;
  pendientes?: number;
};

@Injectable({ providedIn: 'root' })
export class PartidaVentaFlowService {
  constructor(private apiService: ApiService) {}

  generarPartida(venta: { id: number }): Observable<unknown> {
    return this.apiService.store('contabilidad/partida/venta', venta).pipe(
      catchError((err) => this.reintentarTrasAsignacion(err, venta))
    );
  }

  private reintentarTrasAsignacion(err: unknown, venta: { id: number }): Observable<unknown> {
    const body = (err as { error?: DetalleSinProductoPayload })?.error;
    if (body?.code !== 'detalle_sin_producto' || !body.detalle?.id) {
      return throwError(() => err);
    }

    return from(this.pedirAsignacionManual(body)).pipe(
      switchMap((idProducto) => {
        if (!idProducto) {
          return EMPTY;
        }
        return this.apiService.patch('venta/detalle', `${body.detalle.id}/producto`, {
          id_producto: idProducto,
        });
      }),
      switchMap(() => this.generarPartida(venta))
    );
  }

  private async pedirAsignacionManual(payload: DetalleSinProductoPayload): Promise<number | null> {
    const descripcion = payload.detalle?.descripcion ?? '(sin descripción)';
    const sugerencias = payload.sugerencias ?? [];
    const pendientes = payload.pendientes ?? 1;
    let selectedId: number | null = sugerencias[0]?.id ?? null;

    const optionsHtml = sugerencias
      .map(
        (s) =>
          `<option value="${s.id}"${s.id === selectedId ? ' selected' : ''}>${this.escapeHtml(s.nombre)}${
            s.codigo ? ` (${this.escapeHtml(s.codigo)})` : ''
          }</option>`
      )
      .join('');

    const selectBlock =
      sugerencias.length > 0
        ? `<label class="form-label mt-2">Servicio sugerido</label>
           <select id="swal-producto-sugerido" class="form-select">
             ${optionsHtml}
           </select>`
        : `<p class="text-muted small mt-2">No hay sugerencias automáticas. Busque por nombre.</p>`;

    const result = await Swal.fire({
      title: payload.titulo ?? 'Producto no asignado',
      html: `
        <p class="mb-1">${this.escapeHtml(payload.error ?? '')}</p>
        <p><strong>Descripción en la venta:</strong><br>${this.escapeHtml(descripcion)}</p>
        ${pendientes > 1 ? `<p class="text-muted small">Quedan ${pendientes} línea(s) por vincular.</p>` : ''}
        ${selectBlock}
        <label class="form-label mt-3">Buscar servicio</label>
        <input id="swal-buscar-producto" class="form-control" type="text" placeholder="Nombre o código" />
        <div id="swal-resultados" class="list-group mt-2 text-start" style="max-height:160px;overflow:auto;"></div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Asignar y generar partida',
      cancelButtonText: 'Cancelar',
      didOpen: () => {
        const input = document.getElementById('swal-buscar-producto') as HTMLInputElement | null;
        const list = document.getElementById('swal-resultados');
        const select = document.getElementById('swal-producto-sugerido') as HTMLSelectElement | null;

        select?.addEventListener('change', () => {
          selectedId = select.value ? Number(select.value) : null;
        });

        const renderResults = (items: Array<{ id: number; nombre: string; codigo?: string }>) => {
          if (!list) {
            return;
          }
          list.innerHTML = '';
          for (const item of items.slice(0, 8)) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action py-1';
            btn.textContent = item.codigo ? `${item.nombre} (${item.codigo})` : item.nombre;
            btn.addEventListener('click', () => {
              selectedId = item.id;
              if (select) {
                select.innerHTML = `<option value="${item.id}" selected>${item.nombre}</option>`;
              }
            });
            list.appendChild(btn);
          }
        };

        let timer: ReturnType<typeof setTimeout> | undefined;
        input?.addEventListener('input', () => {
          clearTimeout(timer);
          timer = setTimeout(() => {
            const q = (input.value ?? '').trim();
            if (q.length < 2) {
              if (list) {
                list.innerHTML = '';
              }
              return;
            }
            this.apiService.getAll('servicios', { buscador: q, paginate: 8 }).subscribe({
              next: (data: { data?: Array<{ id: number; nombre: string; codigo?: string }> }) => {
                renderResults(Array.isArray(data?.data) ? data.data : []);
              },
            });
          }, 300);
        });
      },
      preConfirm: () => {
        const fromSelect = (
          document.getElementById('swal-producto-sugerido') as HTMLSelectElement | null
        )?.value;
        const id = selectedId ?? (fromSelect ? Number(fromSelect) : null);
        if (!id || Number.isNaN(id)) {
          Swal.showValidationMessage('Seleccione un servicio o producto.');
          return null;
        }
        return id;
      },
    });

    if (!result.isConfirmed || result.value == null) {
      return null;
    }

    return Number(result.value);
  }

  private escapeHtml(text: string): string {
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
}
