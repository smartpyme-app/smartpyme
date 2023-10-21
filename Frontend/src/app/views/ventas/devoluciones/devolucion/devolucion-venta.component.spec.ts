import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DevolucionVentaComponent } from './devolucion-venta.component';

describe('DevolucionVentaComponent', () => {
  let component: DevolucionVentaComponent;
  let fixture: ComponentFixture<DevolucionVentaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DevolucionVentaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DevolucionVentaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
