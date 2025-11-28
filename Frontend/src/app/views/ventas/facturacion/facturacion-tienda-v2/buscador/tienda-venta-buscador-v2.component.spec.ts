import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { TiendaVentaBuscadorV2Component } from './tienda-venta-buscador-v2.component';

describe('TiendaVentaBuscadorV2Component', () => {
  let component: TiendaVentaBuscadorV2Component;
  let fixture: ComponentFixture<TiendaVentaBuscadorV2Component>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ TiendaVentaBuscadorV2Component ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(TiendaVentaBuscadorV2Component);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});

