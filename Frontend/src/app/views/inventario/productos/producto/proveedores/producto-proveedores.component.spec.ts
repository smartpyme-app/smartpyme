import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoProveedoresComponent } from './producto-proveedores.component';

describe('ProductoProveedoresComponent', () => {
  let component: ProductoProveedoresComponent;
  let fixture: ComponentFixture<ProductoProveedoresComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoProveedoresComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoProveedoresComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
