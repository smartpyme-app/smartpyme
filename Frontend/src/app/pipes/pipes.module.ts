import { NgModule } from '@angular/core';
import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { SumPipe } from './sum.pipe';
import { AvgPipe } from './avg.pipe';
import { TruncatePipe } from './truncate.pipe';
import { FilterPipe } from './filter.pipe';
import { SortPipe } from './sort.pipe';
import { CurrencyPipe as CustomCurrencyPipe } from './currency-format.pipe';

@NgModule({
  imports: [
    CommonModule,
    // Los pipes standalone deben estar en imports antes de exportarlos
    SumPipe,
    AvgPipe,
    TruncatePipe,
    FilterPipe,
    SortPipe,
    CustomCurrencyPipe,
  ],
  // Los pipes ahora son standalone, no se declaran aquí
  declarations: [],
  exports: [
    CommonModule,
  	SumPipe,
    AvgPipe,
    TruncatePipe,
    FilterPipe,
    SortPipe,
    CustomCurrencyPipe,
    CurrencyPipe,
    DatePipe,
  ],
  providers: [
    CurrencyPipe,
    DatePipe,
  ]
})
export class PipesModule { }
