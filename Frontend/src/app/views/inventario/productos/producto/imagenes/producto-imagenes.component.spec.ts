import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoImagenesComponent } from './producto-imagenes.component';

describe('ProductoImagenesComponent', () => {
  let component: ProductoImagenesComponent;
  let fixture: ComponentFixture<ProductoImagenesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoImagenesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoImagenesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
