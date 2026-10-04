<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RoleEnum: string
{
    use HasOptions;

    case SuperAdmin = 'Super Admin';
    case HrManager = 'HR Manager';
    case PayrollOfficer = 'Payroll Officer';
    case BranchManager = 'Branch Manager';
    case DeviceAdmin = 'Device Admin';
    case Employee = 'Employee';

    /**
     * The permissions each role receives by default. Super Admin bypasses checks via Gate::before.
     *
     * @return PermissionEnum[]
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => PermissionEnum::cases(),
            self::HrManager => [
                PermissionEnum::EmployeesView,
                PermissionEnum::EmployeesCreate,
                PermissionEnum::EmployeesUpdate,
                PermissionEnum::EmployeesDelete,
                PermissionEnum::SalariesView,
                PermissionEnum::ShiftsView,
                PermissionEnum::ShiftsManage,
                PermissionEnum::HolidaysManage,
                PermissionEnum::AttendanceView,
                PermissionEnum::AttendanceManage,
                PermissionEnum::OvertimeApprove,
                PermissionEnum::LeavesView,
                PermissionEnum::LeavesManage,
                PermissionEnum::LeavesApprove,
                PermissionEnum::AdjustmentsView,
                PermissionEnum::DevicesView,
                PermissionEnum::BiometricsManage,
                PermissionEnum::ReportsView,
            ],
            self::PayrollOfficer => [
                PermissionEnum::EmployeesView,
                PermissionEnum::SalariesView,
                PermissionEnum::SalariesManage,
                PermissionEnum::AttendanceView,
                PermissionEnum::LeavesView,
                PermissionEnum::AdjustmentsView,
                PermissionEnum::AdjustmentsManage,
                PermissionEnum::PayrollView,
                PermissionEnum::PayrollRun,
                PermissionEnum::PayrollPay,
                PermissionEnum::ReportsView,
            ],
            self::BranchManager => [
                PermissionEnum::EmployeesView,
                PermissionEnum::ShiftsView,
                PermissionEnum::AttendanceView,
                PermissionEnum::AttendanceManage,
                PermissionEnum::OvertimeApprove,
                PermissionEnum::LeavesView,
                PermissionEnum::LeavesApprove,
                PermissionEnum::ReportsView,
            ],
            self::DeviceAdmin => [
                PermissionEnum::EmployeesView,
                PermissionEnum::DevicesView,
                PermissionEnum::DevicesManage,
                PermissionEnum::BiometricsManage,
                PermissionEnum::BackupsManage,
            ],
            self::Employee => [
                PermissionEnum::SelfServiceAccess,
            ],
        };
    }
}
