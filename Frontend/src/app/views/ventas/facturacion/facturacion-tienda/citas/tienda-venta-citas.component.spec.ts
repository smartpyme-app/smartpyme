import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { TiendaVentaCitasComponent } from './tienda-venta-citas.component';

describe('TiendaVentaCitasComponent', () => {
  let component: TiendaVentaCitasComponent;
  let fixture: ComponentFixture<TiendaVentaCitasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ TiendaVentaCitasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(TiendaVentaCitasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
