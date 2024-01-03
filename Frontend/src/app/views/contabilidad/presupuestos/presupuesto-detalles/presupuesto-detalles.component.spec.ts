import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { PresupuestoDetallesComponent } from './presupuesto-detalles.component';

describe('PresupuestoDetallesComponent', () => {
  let component: PresupuestoDetallesComponent;
  let fixture: ComponentFixture<PresupuestoDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ PresupuestoDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(PresupuestoDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
