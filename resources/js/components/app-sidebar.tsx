import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    BarChart3,
    BookOpen,
    CalendarCheck,
    CircleUserRound,
    CalendarClock,
    Fingerprint,
    LayoutGrid,
    Plane,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { filterNavByPermissions } from '@/lib/filter-nav-items';
import type { NavItem } from '@/types';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import OvertimeController from '@/actions/App/Http/Controllers/Attendance/OvertimeController';
import LeaveBalanceController from '@/actions/App/Http/Controllers/Leaves/LeaveBalanceController';
import LeaveRequestController from '@/actions/App/Http/Controllers/Leaves/LeaveRequestController';
import LeaveTypeController from '@/actions/App/Http/Controllers/Leaves/LeaveTypeController';
import PayrollAdjustmentController from '@/actions/App/Http/Controllers/Payroll/PayrollAdjustmentController';
import PayrollRunController from '@/actions/App/Http/Controllers/Payroll/PayrollRunController';
import SalaryAdvanceController from '@/actions/App/Http/Controllers/Payroll/SalaryAdvanceController';
import DeviceBackupController from '@/actions/App/Http/Controllers/Backups/DeviceBackupController';
import DeviceController from '@/actions/App/Http/Controllers/Devices/DeviceController';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
import MyPortalController from '@/actions/App/Http/Controllers/SelfService/MyPortalController';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import UnmatchedPunchController from '@/actions/App/Http/Controllers/Devices/UnmatchedPunchController';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import BranchController from '@/actions/App/Http/Controllers/Organisation/BranchController';
import CompanySettingsController from '@/actions/App/Http/Controllers/Organisation/CompanySettingsController';
import DepartmentController from '@/actions/App/Http/Controllers/Organisation/DepartmentController';
import DesignationController from '@/actions/App/Http/Controllers/Organisation/DesignationController';
import SalaryComponentController from '@/actions/App/Http/Controllers/Organisation/SalaryComponentController';
import HolidayController from '@/actions/App/Http/Controllers/Shifts/HolidayController';
import ShiftAssignmentController from '@/actions/App/Http/Controllers/Shifts/ShiftAssignmentController';
import ShiftController from '@/actions/App/Http/Controllers/Shifts/ShiftController';
import { dashboard, guide } from '@/routes';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'My Portal',
        href: MyPortalController.index().url,
        icon: CircleUserRound,
        permission: 'self-service.access',
    },
    {
        title: 'Employees',
        href: EmployeeController.index().url,
        icon: Users,
        permission: 'employees.view',
    },
    {
        title: 'Attendance',
        href: AttendanceController.index().url,
        icon: CalendarCheck,
        children: [
            {
                title: 'Daily',
                href: AttendanceController.index().url,
                permission: 'attendance.view',
            },
            {
                title: 'Monthly Register',
                href: AttendanceController.register().url,
                permission: 'attendance.view',
            },
            {
                title: 'Overtime Approval',
                href: OvertimeController.index().url,
                permission: 'overtime.approve',
            },
        ],
    },
    {
        title: 'Leave',
        href: LeaveRequestController.index().url,
        icon: Plane,
        children: [
            {
                title: 'Requests',
                href: LeaveRequestController.index().url,
                permission: 'leaves.view',
            },
            {
                title: 'Balances',
                href: LeaveBalanceController.index().url,
                permission: 'leaves.view',
            },
            {
                title: 'Leave Types',
                href: LeaveTypeController.index().url,
                permission: 'leaves.view',
            },
        ],
    },
    {
        title: 'Payroll',
        href: PayrollRunController.index().url,
        icon: Wallet,
        children: [
            {
                title: 'Payroll Runs',
                href: PayrollRunController.index().url,
                permission: 'payroll.view',
            },
            {
                title: 'Bonuses & Deductions',
                href: PayrollAdjustmentController.index().url,
                permission: 'adjustments.view',
            },
            {
                title: 'Advances & Loans',
                href: SalaryAdvanceController.index().url,
                permission: 'adjustments.view',
            },
        ],
    },
    {
        title: 'Reports',
        href: ReportController.index().url,
        icon: BarChart3,
        permission: 'reports.view',
    },
    {
        title: 'Shifts & Roster',
        href: ShiftAssignmentController.index().url,
        icon: CalendarClock,
        children: [
            {
                title: 'Roster',
                href: ShiftAssignmentController.index().url,
                permission: 'shifts.view',
            },
            {
                title: 'Shifts',
                href: ShiftController.index().url,
                permission: 'shifts.view',
            },
            {
                title: 'Holidays',
                href: HolidayController.index().url,
                permission: 'shifts.view',
            },
        ],
    },
    {
        title: 'Devices',
        href: DeviceController.index().url,
        icon: Fingerprint,
        children: [
            {
                title: 'All Devices',
                href: DeviceController.index().url,
                permission: 'devices.view',
            },
            {
                title: 'Unmatched Scans',
                href: UnmatchedPunchController.index().url,
                permission: 'devices.view',
            },
            {
                title: 'Backups & Restore',
                href: DeviceBackupController.index().url,
                permission: 'backups.manage',
            },
        ],
    },
    {
        title: 'Organisation',
        href: BranchController.index().url,
        icon: Building2,
        children: [
            {
                title: 'Branches',
                href: BranchController.index().url,
                permission: 'organisation.manage',
            },
            {
                title: 'Departments',
                href: DepartmentController.index().url,
                permission: 'organisation.manage',
            },
            {
                title: 'Designations',
                href: DesignationController.index().url,
                permission: 'organisation.manage',
            },
            {
                title: 'Salary Components',
                href: SalaryComponentController.index().url,
                permission: 'salaries.view',
            },
            {
                title: 'Users & Access',
                href: UserController.index().url,
                permission: 'users.manage',
            },
            {
                title: 'Company Settings',
                href: CompanySettingsController.edit().url,
                permission: 'settings.manage',
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'User Guide',
        href: guide(),
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;

    const navItems = useMemo(
        () => filterNavByPermissions(mainNavItems, auth.permissions ?? []),
        [auth.permissions],
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
