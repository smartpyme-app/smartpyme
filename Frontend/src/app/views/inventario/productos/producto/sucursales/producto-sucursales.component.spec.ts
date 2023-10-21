import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoSucursalesComponent } from './producto-sucursales.component';

describe('ProductoSucursalesComponent', () => {
  let component: ProductoSucursalesComponent;
  let fixture: ComponentFixture<ProductoSucursalesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoSucursalesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoSucursalesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
