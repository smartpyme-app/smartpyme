import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { TiendaVentaPaquetesV2Component } from './tienda-venta-paquetes-v2.component';

describe('TiendaVentaPaquetesV2Component', () => {
  let component: TiendaVentaPaquetesV2Component;
  let fixture: ComponentFixture<TiendaVentaPaquetesV2Component>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ TiendaVentaPaquetesV2Component ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(TiendaVentaPaquetesV2Component);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});

