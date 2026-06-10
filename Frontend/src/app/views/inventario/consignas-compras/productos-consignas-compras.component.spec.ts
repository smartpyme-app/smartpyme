import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductosConsignasComprasComponent } from './productos-consignas-compras.component';

describe('ProductosConsignasComprasComponent', () => {
  let component: ProductosConsignasComprasComponent;
  let fixture: ComponentFixture<ProductosConsignasComprasComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ ProductosConsignasComprasComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ProductosConsignasComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
