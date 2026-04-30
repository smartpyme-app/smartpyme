import { async, ComponentFixture, TestBed } from '@angular/core/testing';
import { NO_ERRORS_SCHEMA } from '@angular/core';

import { GastosComponent } from './gastos.component';

describe('GastosComponent', () => {
  let component: GastosComponent;
  let fixture: ComponentFixture<GastosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ GastosComponent ],
      schemas: [NO_ERRORS_SCHEMA],
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
