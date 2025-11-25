import { Component, OnInit, OnDestroy, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import * as moment from 'moment';

@Component({
    selector: 'app-timer',
    templateUrl: './timer.component.html',
    standalone: true,
    imports: [CommonModule]
})
export class TimerComponent implements OnInit, OnDestroy {

    @Input() time:any;
    public timeAgo:number = 0;
    public timeResfresh:any;

    constructor() { }

    ngOnInit() {
        const today = new Date();
        this.timeAgo = moment(today).diff(this.time, "minutes");;
        console.log(this.time);
        this.timeResfresh = setInterval(()=> {
                this.loadAll();
        }, 60000);
    }

    loadAll(){
        const today = new Date();
        this.timeAgo = moment(today).diff(this.time, "minutes");;
        console.log(this.timeAgo);
    }

    ngOnDestroy() {
        clearInterval(this.timeResfresh);
    }
}
