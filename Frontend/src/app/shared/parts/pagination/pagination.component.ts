import { Component, OnInit, EventEmitter, Output, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
    selector: 'app-pagination',
    templateUrl: './pagination.component.html',
    standalone: true,
    imports: [CommonModule]
})
export class PaginationComponent implements OnInit {

	@Output() setPagination = new EventEmitter();
	@Input() items:any = [];

	constructor() { }

	ngOnInit() {}

    setPage(page:number){
		this.setPagination.emit({page: page});
	}

}
