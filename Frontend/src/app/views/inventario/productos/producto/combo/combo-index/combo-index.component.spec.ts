import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ComboIndexComponent } from './combo-index.component';

describe('ComboIndexComponent', () => {
  let component: ComboIndexComponent;
  let fixture: ComponentFixture<ComboIndexComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ ComboIndexComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ComboIndexComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
