import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { VentaDetallesV2Component } from './venta-detalles-v2.component';

describe('VentaDetallesV2Component', () => {
  let component: VentaDetallesV2Component;
  let fixture: ComponentFixture<VentaDetallesV2Component>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ VentaDetallesV2Component ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(VentaDetallesV2Component);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});

