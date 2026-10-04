<?php

use App\Http\Controllers\Attendance\AttendanceAdjustmentController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\OvertimeController;
use App\Http\Controllers\Backups\DeviceBackupController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Devices\DeviceController;
use App\Http\Controllers\Devices\UnmatchedPunchController;
use App\Http\Controllers\Employees\EmployeeBiometricController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeSalaryController;
use App\Http\Controllers\Leaves\LeaveBalanceController;
use App\Http\Controllers\Leaves\LeaveRequestController;
use App\Http\Controllers\Leaves\LeaveTypeController;
use App\Http\Controllers\Organisation\BranchController;
use App\Http\Controllers\Organisation\CompanySettingsController;
use App\Http\Controllers\Organisation\DepartmentController;
use App\Http\Controllers\Organisation\DesignationController;
use App\Http\Controllers\Organisation\SalaryComponentController;
use App\Http\Controllers\Payroll\PayrollAdjustmentController;
use App\Http\Controllers\Payroll\PayrollRunController;
use App\Http\Controllers\Payroll\SalaryAdvanceController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\SelfService\MyPortalController;
use App\Http\Controllers\Shifts\HolidayController;
use App\Http\Controllers\Shifts\RosterOverrideController;
use App\Http\Controllers\Shifts\ShiftAssignmentController;
use App\Http\Controllers\Shifts\ShiftController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::inertia('guide', 'guide')->name('guide');

    Route::get('my', [MyPortalController::class, 'index'])->name('my.index');
    Route::get('my/payslips/{payslip}/pdf', [MyPortalController::class, 'payslip'])->name('my.payslips.pdf');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::prefix('organisation')->group(function () {
        Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('designations', DesignationController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('salary-components', SalaryComponentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('settings', [CompanySettingsController::class, 'edit'])->name('company-settings.edit');
        Route::put('settings', [CompanySettingsController::class, 'update'])->name('company-settings.update');
    });

    Route::resource('shifts', ShiftController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('roster', [ShiftAssignmentController::class, 'index'])->name('roster.index');
    Route::resource('shift-assignments', ShiftAssignmentController::class)->only(['store', 'update', 'destroy']);
    Route::resource('roster-overrides', RosterOverrideController::class)->only(['store', 'destroy']);
    Route::resource('holidays', HolidayController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('register', [AttendanceController::class, 'register'])->name('attendance.register');
        Route::get('employees/{employee}/punches', [AttendanceController::class, 'punches'])->name('attendance.punches');
        Route::post('punches', [AttendanceAdjustmentController::class, 'storePunch'])->name('attendance.punches.store');
        Route::post('punches/{punch}/void', [AttendanceAdjustmentController::class, 'voidPunch'])->name('attendance.punches.void');
        Route::post('days/{attendanceDay}/decision', [AttendanceAdjustmentController::class, 'decide'])->name('attendance.days.decide');
        Route::post('rebuild', [AttendanceAdjustmentController::class, 'rebuild'])->name('attendance.rebuild');
        Route::get('overtime', [OvertimeController::class, 'index'])->name('overtime.index');
        Route::post('overtime/{attendanceDay}', [OvertimeController::class, 'decide'])->name('overtime.decide');
    });

    Route::prefix('leaves')->group(function () {
        Route::get('/', [LeaveRequestController::class, 'index'])->name('leaves.index');
        Route::post('/', [LeaveRequestController::class, 'store'])->name('leaves.store');
        Route::post('{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
        Route::post('{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leaves.reject');
        Route::post('{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leaves.cancel');
        Route::get('{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment'])->name('leaves.attachment');
        Route::get('balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
        Route::put('balances/{leaveBalance}', [LeaveBalanceController::class, 'adjust'])->name('leave-balances.adjust');
        Route::resource('types', LeaveTypeController::class)->only(['index', 'store', 'update', 'destroy'])
            ->names('leave-types')->parameters(['types' => 'leaveType']);
    });

    Route::prefix('payroll')->group(function () {
        Route::get('adjustments', [PayrollAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::post('adjustments', [PayrollAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::delete('adjustments/{payrollAdjustment}', [PayrollAdjustmentController::class, 'destroy'])->name('adjustments.destroy');
        Route::get('advances', [SalaryAdvanceController::class, 'index'])->name('advances.index');
        Route::post('advances', [SalaryAdvanceController::class, 'store'])->name('advances.store');
        Route::post('advances/{salaryAdvance}/repay', [SalaryAdvanceController::class, 'repay'])->name('advances.repay');
        Route::post('advances/{salaryAdvance}/cancel', [SalaryAdvanceController::class, 'cancel'])->name('advances.cancel');

        Route::get('/', [PayrollRunController::class, 'index'])->name('payroll.index');
        Route::post('/', [PayrollRunController::class, 'store'])->name('payroll.store');
        Route::get('{payrollRun}', [PayrollRunController::class, 'show'])->name('payroll.show');
        Route::post('{payrollRun}/regenerate', [PayrollRunController::class, 'regenerate'])->name('payroll.regenerate');
        Route::post('{payrollRun}/approve', [PayrollRunController::class, 'approve'])->name('payroll.approve');
        Route::post('{payrollRun}/revert', [PayrollRunController::class, 'revert'])->name('payroll.revert');
        Route::post('{payrollRun}/pay', [PayrollRunController::class, 'markPaid'])->name('payroll.pay');
        Route::post('{payrollRun}/cancel', [PayrollRunController::class, 'cancel'])->name('payroll.cancel');
        Route::get('{payrollRun}/bank-sheet', [PayrollRunController::class, 'bankSheet'])->name('payroll.bank-sheet');
        Route::get('{payrollRun}/register', [PayrollRunController::class, 'register'])->name('payroll.register');
        Route::get('{payrollRun}/payslips/{payslip}/pdf', [PayrollRunController::class, 'payslipPdf'])->name('payroll.payslips.pdf')->scopeBindings();
    });

    Route::prefix('backups')->group(function () {
        Route::get('/', [DeviceBackupController::class, 'index'])->name('backups.index');
        Route::post('/', [DeviceBackupController::class, 'store'])->name('backups.store');
        Route::post('upload', [DeviceBackupController::class, 'upload'])->name('backups.upload');
        Route::post('restore', [DeviceBackupController::class, 'restore'])->name('backups.restore');
        Route::get('{deviceBackup}/download', [DeviceBackupController::class, 'download'])->name('backups.download');
        Route::post('{deviceBackup}/import-logs', [DeviceBackupController::class, 'importLogs'])->name('backups.import-logs');
        Route::delete('{deviceBackup}', [DeviceBackupController::class, 'destroy'])->name('backups.destroy');
    });

    Route::get('devices/unmatched-punches', [UnmatchedPunchController::class, 'index'])->name('unmatched-punches.index');
    Route::post('devices/unmatched-punches/assign', [UnmatchedPunchController::class, 'assign'])->name('unmatched-punches.assign');
    Route::resource('devices', DeviceController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::post('devices/{device}/actions', [DeviceController::class, 'action'])->name('devices.action');
    Route::post('devices/{device}/commands/{command}/retry', [DeviceController::class, 'retryCommand'])->name('devices.commands.retry')->scopeBindings();
    Route::post('devices/{device}/commands/{command}/cancel', [DeviceController::class, 'cancelCommand'])->name('devices.commands.cancel')->scopeBindings();

    Route::resource('employees', EmployeeController::class);
    Route::post('employees/{employee}/salaries', [EmployeeSalaryController::class, 'store'])->name('employees.salaries.store');
    Route::delete('employees/{employee}/salaries/{salary}', [EmployeeSalaryController::class, 'destroy'])->name('employees.salaries.destroy')->scopeBindings();
    Route::post('employees/{employee}/biometrics/push', [EmployeeBiometricController::class, 'push'])->name('employees.biometrics.push');
    Route::post('employees/{employee}/biometrics/remove', [EmployeeBiometricController::class, 'remove'])->name('employees.biometrics.remove');
    Route::delete('employees/{employee}/biometrics/templates/{biometricTemplate}', [EmployeeBiometricController::class, 'destroyTemplate'])->name('employees.biometrics.templates.destroy')->scopeBindings();
});

require __DIR__.'/settings.php';
