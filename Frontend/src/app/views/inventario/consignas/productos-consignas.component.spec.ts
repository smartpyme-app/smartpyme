import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductosConsignasComponent } from './productos-consignas.component';

describe('ProductosConsignasComponent', () => {
  let component: ProductosConsignasComponent;
  let fixture: ComponentFixture<ProductosConsignasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductosConsignasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductosConsignasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
