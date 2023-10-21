import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DetalleComprasComponent } from './detalle-compras.component';

describe('DetalleComprasComponent', () => {
  let component: DetalleComprasComponent;
  let fixture: ComponentFixture<DetalleComprasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DetalleComprasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DetalleComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
