<?php

use Illuminate\Support\Facades\Schedule;

/*
| Attendance statuses depend on the clock (a shift becomes "absent" or "missing check-out" once its
| window passes), so yesterday and today are rebuilt every hour.
*/
Schedule::command('attendance:rebuild')->hourlyAt(5)->withoutOverlapping()->onOneServer();

Schedule::command('leaves:allocate')->yearlyOn(1, 1, '00:30')->onOneServer();

Schedule::command('devices:finalize-backups')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('devices:auto-backup')->dailyAt('02:00')->onOneServer();
