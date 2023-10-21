import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoPromocionesComponent } from './producto-promociones.component';

describe('ProductoPromocionesComponent', () => {
  let component: ProductoPromocionesComponent;
  let fixture: ComponentFixture<ProductoPromocionesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoPromocionesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoPromocionesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
