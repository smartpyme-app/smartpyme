import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SumPipe } from './sum.pipe';
import { AvgPipe } from './avg.pipe';
import { TruncatePipe } from './truncate.pipe';
import { FilterPipe } from './filter.pipe';
import { SortPipe } from './sort.pipe';
import { CurrencyPipe } from './currency-format.pipe';

@NgModule({
  imports: [
    CommonModule,
  ],
  declarations: [
  	SumPipe,
    AvgPipe,
    TruncatePipe,
    FilterPipe,
    SortPipe,
    CurrencyPipe,
  ],
  exports: [
  	SumPipe,
    AvgPipe,
    TruncatePipe,
    FilterPipe,
    SortPipe,
    CurrencyPipe,
  ]
})
export class PipesModule { }
