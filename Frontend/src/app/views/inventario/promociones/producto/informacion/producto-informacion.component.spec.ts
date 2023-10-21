import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductoInformacionComponent } from './producto-informacion.component';

describe('ProductoInformacionComponent', () => {
  let component: ProductoInformacionComponent;
  let fixture: ComponentFixture<ProductoInformacionComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ProductoInformacionComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ProductoInformacionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
