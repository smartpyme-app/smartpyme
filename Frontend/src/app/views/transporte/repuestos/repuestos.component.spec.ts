import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { RepuestosComponent } from './repuestos.component';

describe('RepuestosComponent', () => {
  let component: RepuestosComponent;
  let fixture: ComponentFixture<RepuestosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ RepuestosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(RepuestosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
