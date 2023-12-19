import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearAbonoVentaComponent } from './crear-abono-venta.component';

describe('CrearAbonoVentaComponent', () => {
  let component: CrearAbonoVentaComponent;
  let fixture: ComponentFixture<CrearAbonoVentaComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CrearAbonoVentaComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CrearAbonoVentaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
