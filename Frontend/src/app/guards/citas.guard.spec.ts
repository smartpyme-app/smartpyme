import { TestBed, async, inject } from '@angular/core/testing';

import { CitasGuard } from './citas.guard';

describe('CitasGuard', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [CitasGuard]
    });
  });

  it('should ...', inject([CitasGuard], (guard: CitasGuard) => {
    expect(guard).toBeTruthy();
  }));
});
