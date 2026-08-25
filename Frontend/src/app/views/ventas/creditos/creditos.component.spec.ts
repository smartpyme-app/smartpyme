import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { RouterTestingModule } from '@angular/router/testing';
import { CreditosComponent } from './creditos.component';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

describe('CreditosComponent', () => {
  let fixture: ComponentFixture<CreditosComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CreditosComponent, RouterTestingModule],
      providers: [
        {
          provide: ApiService,
          useValue: {
            isVentasLimitado: () => false,
            getAll: (url: string) => {
              if (url === 'creditos-clientes/cola') {
                return of({ data: [], total: 0 });
              }
              return of({ data: [] });
            },
          },
        },
        { provide: AlertService, useValue: { error: () => undefined } },
      ],
    }).compileComponents();
    fixture = TestBed.createComponent(CreditosComponent);
    fixture.detectChanges();
  });

  it('muestra el listado vacío', () => {
    expect(fixture.nativeElement.textContent).toContain('Sin créditos');
  });

  it('muestra cola vacía de cuotas por facturar', () => {
    expect(fixture.nativeElement.textContent).toContain('Sin cuotas por facturar');
  });

  it('muestra el botón de alta si no es Ventas Limitado', () => {
    expect(fixture.nativeElement.textContent).toContain('Añadir crédito');
  });
});

describe('CreditosComponent cola', () => {
  it('lista cuotas vencidas con enlace a facturar', async () => {
    await TestBed.configureTestingModule({
      imports: [CreditosComponent, RouterTestingModule],
      providers: [
        {
          provide: ApiService,
          useValue: {
            isVentasLimitado: () => false,
            getAll: (url: string) => {
              if (url === 'creditos-clientes/cola') {
                return of({
                  data: [{
                    id: 1,
                    id_contrato: 9,
                    numero: 1,
                    monto: 50,
                    fecha_vencimiento: '2026-08-20',
                    estado_cola: 'vencida',
                    cliente: { nombre: 'Ana', nombre_completo: 'Ana Pérez' },
                  }],
                  total: 1,
                });
              }
              return of({ data: [] });
            },
          },
        },
        { provide: AlertService, useValue: { error: () => undefined } },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(CreditosComponent);
    fixture.detectChanges();
    const html = fixture.nativeElement.textContent as string;
    expect(html).toContain('Vencida');
    expect(html).toContain('Facturar');
    expect(fixture.nativeElement.querySelector('a[href*="venta/crear"]')).toBeTruthy();
  });
});
