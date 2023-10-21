import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoPreciosComponent } from './producto-precios.component';

describe('ProductoPreciosComponent', () => {
  let component: ProductoPreciosComponent;
  let fixture: ComponentFixture<ProductoPreciosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoPreciosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoPreciosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
