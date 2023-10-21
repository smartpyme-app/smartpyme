import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { GastosComponent } from './gastos.component';

describe('GastosComponent', () => {
  let component: GastosComponent;
  let fixture: ComponentFixture<GastosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ GastosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(GastosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
