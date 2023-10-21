import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { MantenimientoDetallesComponent } from './mantenimiento-detalles.component';

describe('MantenimientoDetallesComponent', () => {
  let component: MantenimientoDetallesComponent;
  let fixture: ComponentFixture<MantenimientoDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ MantenimientoDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(MantenimientoDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
