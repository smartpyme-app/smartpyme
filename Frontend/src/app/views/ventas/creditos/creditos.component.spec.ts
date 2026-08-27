import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { RouterTestingModule } from '@angular/router/testing';
import { CreditosComponent } from './creditos.component';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

function apiMock(payload: any = { data: [], total: 0 }) {
  return {
    isVentasLimitado: () => false,
    auth_user: () => ({ empresa: { moneda: 'USD' } }),
    getAll: () => of(payload),
  };
}

describe('CreditosComponent', () => {
  let fixture: ComponentFixture<CreditosComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CreditosComponent, RouterTestingModule],
      providers: [
        { provide: ApiService, useValue: apiMock() },
        { provide: AlertService, useValue: { error: () => undefined } },
      ],
    }).compileComponents();
    fixture = TestBed.createComponent(CreditosComponent);
    fixture.detectChanges();
  });

  it('muestra el listado vacío con el diseño de clientes', () => {
    const html = fixture.nativeElement.textContent as string;
    expect(html).toContain('No tiene créditos registrados');
    expect(html).toContain('Cuentas por cobrar');
    expect(html).toContain('Créditos');
  });

  it('no muestra alta ni cola: los contratos salen de facturación', () => {
    const html = fixture.nativeElement.textContent as string;
    expect(html).not.toContain('Añadir crédito');
    expect(html).not.toContain('Sin cuotas por facturar');
  });
});

describe('CreditosComponent contratos', () => {
  it('lista contratos con enlace al detalle', async () => {
    await TestBed.configureTestingModule({
      imports: [CreditosComponent, RouterTestingModule],
      providers: [
        {
          provide: ApiService,
          useValue: apiMock({
            data: [{
              id: 9,
              id_cliente: 3,
              tipo: 'bien',
              monto: 90,
              n_cuotas: 3,
              cuotas_hechas: 2,
              fecha_inicio: '2026-01-15',
              estado: 'activo',
              cliente: { nombre: 'Ana', nombre_completo: 'Ana Pérez' },
            }],
            total: 1,
            last_page: 1,
          }),
        },
        { provide: AlertService, useValue: { error: () => undefined } },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(CreditosComponent);
    fixture.detectChanges();
    const html = fixture.nativeElement.textContent as string;
    expect(html).toContain('Ana Pérez');
    expect(html).not.toContain('Tipo');
    expect(html).toContain('Activo');
    expect(html).toContain('2/3');
    expect(html).not.toContain('No tiene créditos registrados');
  });
});
