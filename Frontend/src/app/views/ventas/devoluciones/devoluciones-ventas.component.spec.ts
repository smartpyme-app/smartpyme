import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DevolucionesVentasComponent } from './devoluciones-ventas.component';

describe('DevolucionesVentasComponent', () => {
  let component: DevolucionesVentasComponent;
  let fixture: ComponentFixture<DevolucionesVentasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DevolucionesVentasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DevolucionesVentasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
