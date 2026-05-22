import {
  Component,
  EventEmitter,
  Input,
  OnDestroy,
  Output,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { of, Subject, Subscription } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, filter, switchMap } from 'rxjs/operators';

@Component({
  selector: 'app-buscador-cliente-vista360',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './buscador-cliente-vista360.component.html',
  styleUrls: ['./buscador-cliente-vista360.component.css'],
})
export class BuscadorClienteVista360Component implements OnDestroy {
  @Input() excludeClienteId: number | string | null = null;
  @Output() selectCliente = new EventEmitter<any>();

  public term = '';
  public resultados: any[] = [];
  public searching = false;
  public searchExecuted = false;
  public abierto = false;

  private readonly search$ = new Subject<string>();
  private searchSub?: Subscription;

  constructor(
    private apiService: ApiService,
    private alertService: AlertService
  ) {
    this.searchSub = this.search$
      .pipe(
        debounceTime(400),
        distinctUntilChanged(),
        filter((value) => value.length > 2),
        switchMap((value) => {
          this.searching = true;
          this.searchExecuted = true;
          return this.apiService.getAll('clientes/buscar/' + encodeURIComponent(value)).pipe(
            catchError((error) => {
              this.alertService.error(error);
              return of([]);
            })
          );
        })
      )
      .subscribe((clientes) => {
        this.resultados = this.filtrarResultados(clientes);
        this.searching = false;
        this.abierto = this.resultados.length > 0;
      });
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
  }

  onTermChange(): void {
    const value = (this.term || '').trim();
    if (value.length <= 2) {
      this.resultados = [];
      this.searchExecuted = value.length > 0;
      this.abierto = false;
      this.searching = false;
      return;
    }
    this.search$.next(value);
  }

  seleccionar(cliente: any): void {
    this.term = '';
    this.resultados = [];
    this.searchExecuted = false;
    this.abierto = false;
    this.selectCliente.emit(cliente);
  }

  cerrarResultados(): void {
    this.abierto = false;
  }

  nombreCliente(cliente: any): string {
    if (cliente?.tipo === 'Empresa' && cliente?.nombre_empresa) {
      return cliente.nombre_empresa;
    }
    return cliente?.nombre_completo || cliente?.nombre || 'Sin nombre';
  }

  detalleCliente(cliente: any): string {
    const partes: string[] = [];
    if (cliente?.dui) {
      partes.push(`DUI: ${cliente.dui}`);
    }
    if (cliente?.nit) {
      partes.push(`NIT: ${cliente.nit}`);
    } else if (cliente?.ncr) {
      partes.push(`NRC: ${cliente.ncr}`);
    }
    if (cliente?.telefono) {
      partes.push(`Tel: ${cliente.telefono}`);
    }
    return partes.join(' · ');
  }

  private filtrarResultados(clientes: any): any[] {
    const lista = Array.isArray(clientes) ? clientes : [];
    if (!this.excludeClienteId) {
      return lista;
    }
    return lista.filter(
      (c) => String(c?.id) !== String(this.excludeClienteId)
    );
  }
}
