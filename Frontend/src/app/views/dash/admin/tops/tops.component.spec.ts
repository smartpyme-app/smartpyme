import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { TopsComponent } from './tops.component';

describe('TopsComponent', () => {
  let component: TopsComponent;
  let fixture: ComponentFixture<TopsComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ TopsComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(TopsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
