import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { TiendaVentaPaquetesComponent } from './tienda-venta-paquetes.component';

describe('TiendaVentaPaquetesComponent', () => {
  let component: TiendaVentaPaquetesComponent;
  let fixture: ComponentFixture<TiendaVentaPaquetesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ TiendaVentaPaquetesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(TiendaVentaPaquetesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
