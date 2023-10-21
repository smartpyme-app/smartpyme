import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoComprasComponent } from './producto-compras.component';

describe('ProductoComprasComponent', () => {
  let component: ProductoComprasComponent;
  let fixture: ComponentFixture<ProductoComprasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoComprasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
