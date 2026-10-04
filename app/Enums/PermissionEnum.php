<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PermissionEnum: string
{
    use HasOptions;

    // Organisation
    case OrganisationManage = 'organisation.manage';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';

    // Employees
    case EmployeesView = 'employees.view';
    case EmployeesCreate = 'employees.create';
    case EmployeesUpdate = 'employees.update';
    case EmployeesDelete = 'employees.delete';

    // Salaries
    case SalariesView = 'salaries.view';
    case SalariesManage = 'salaries.manage';

    // Shifts & calendar
    case ShiftsView = 'shifts.view';
    case ShiftsManage = 'shifts.manage';
    case HolidaysManage = 'holidays.manage';

    // Attendance
    case AttendanceView = 'attendance.view';
    case AttendanceManage = 'attendance.manage';
    case OvertimeApprove = 'overtime.approve';

    // Leaves
    case LeavesView = 'leaves.view';
    case LeavesManage = 'leaves.manage';
    case LeavesApprove = 'leaves.approve';

    // Bonuses, deductions & advances
    case AdjustmentsView = 'adjustments.view';
    case AdjustmentsManage = 'adjustments.manage';

    // Payroll
    case PayrollView = 'payroll.view';
    case PayrollRun = 'payroll.run';
    case PayrollApprove = 'payroll.approve';
    case PayrollPay = 'payroll.pay';

    // Devices & biometrics
    case DevicesView = 'devices.view';
    case DevicesManage = 'devices.manage';
    case BiometricsManage = 'biometrics.manage';
    case BackupsManage = 'backups.manage';

    // Reports
    case ReportsView = 'reports.view';

    // Employee self-service
    case SelfServiceAccess = 'self-service.access';
}
