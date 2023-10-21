import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { GalonajeComponent } from './galonaje.component';

describe('GalonajeComponent', () => {
  let component: GalonajeComponent;
  let fixture: ComponentFixture<GalonajeComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ GalonajeComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(GalonajeComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
