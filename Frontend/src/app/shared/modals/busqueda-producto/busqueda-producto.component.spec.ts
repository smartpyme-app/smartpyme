import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { BusquedaProductoComponent } from './busqueda-producto.component';

describe('BusquedaProductoComponent', () => {
  let component: BusquedaProductoComponent;
  let fixture: ComponentFixture<BusquedaProductoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ BusquedaProductoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(BusquedaProductoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
