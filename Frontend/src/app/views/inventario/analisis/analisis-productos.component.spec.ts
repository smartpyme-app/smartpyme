import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { AnalisisProductosComponent } from './analisis-productos.component';

describe('AnalisisProductosComponent', () => {
  let component: AnalisisProductosComponent;
  let fixture: ComponentFixture<AnalisisProductosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ AnalisisProductosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(AnalisisProductosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
