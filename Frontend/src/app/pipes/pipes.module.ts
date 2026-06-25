import { NgModule } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { SumPipe } from './sum.pipe';
import { AvgPipe } from './avg.pipe';
import { TruncatePipe } from './truncate.pipe';
import { FilterPipe } from './filter.pipe';
import { SortPipe } from './sort.pipe';
import { CurrencyPipe } from './currency-format.pipe';
import { TranslatePipe } from '@ngx-translate/core';

@NgModule({
  imports: [
    CommonModule,
    SumPipe,
    AvgPipe,
    TruncatePipe,
    FilterPipe,
    SortPipe,
    CurrencyPipe,
    TranslatePipe,
  ],
  declarations: [],
  exports: [
    // No exportar CommonModule para evitar conflicto con nuestro CurrencyPipe custom
  	SumPipe,
    AvgPipe,
    TruncatePipe,
    FilterPipe,
    SortPipe,
    CurrencyPipe,
    TranslatePipe,
    DatePipe,
  ],
  providers: [
    DatePipe,
  ]
})
export class PipesModule { }
