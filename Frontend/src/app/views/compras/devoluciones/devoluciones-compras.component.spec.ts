import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DevolucionesComprasComponent } from './devoluciones-compras.component';

describe('DevolucionesComprasComponent', () => {
  let component: DevolucionesComprasComponent;
  let fixture: ComponentFixture<DevolucionesComprasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DevolucionesComprasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DevolucionesComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
