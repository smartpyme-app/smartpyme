import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { RetaceoComponent } from './retaceo.component';

describe('RetaceoComponent', () => {
  let component: RetaceoComponent;
  let fixture: ComponentFixture<RetaceoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ RetaceoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(RetaceoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
