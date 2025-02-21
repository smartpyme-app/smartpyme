import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { VentaDetallesComponent } from './venta-detalles.component';

describe('VentaDetallesComponent', () => {
  let component: VentaDetallesComponent;
  let fixture: ComponentFixture<VentaDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ VentaDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(VentaDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
