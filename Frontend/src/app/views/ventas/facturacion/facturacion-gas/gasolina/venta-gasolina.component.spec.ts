import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { VentaGasolinaComponent } from './venta-gasolina.component';

describe('VentaGasolinaComponent', () => {
  let component: VentaGasolinaComponent;
  let fixture: ComponentFixture<VentaGasolinaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ VentaGasolinaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(VentaGasolinaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
