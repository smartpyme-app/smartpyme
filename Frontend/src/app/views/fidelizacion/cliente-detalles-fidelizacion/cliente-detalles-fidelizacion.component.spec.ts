import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ClienteDetallesFidelizacionComponent } from './cliente-detalles-fidelizacion.component';

describe('ClienteDetallesFidelizacionComponent', () => {
  let component: ClienteDetallesFidelizacionComponent;
  let fixture: ComponentFixture<ClienteDetallesFidelizacionComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ ClienteDetallesFidelizacionComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ClienteDetallesFidelizacionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
