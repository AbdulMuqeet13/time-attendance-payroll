import {
    BarChart3,
    Building2,
    CalendarCheck,
    CalendarClock,
    CircleUserRound,
    Clock,
    Cpu,
    DatabaseBackup,
    Fingerprint,
    HandCoins,
    HelpCircle,
    LayoutGrid,
    ListChecks,
    Plane,
    Rocket,
    ScanLine,
    ShieldCheck,
    Table2,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Bullets,
    GuideTable,
    Path,
    See,
    Steps,
    Sub,
    Tip,
    Warning,
} from '@/components/guide/guide-blocks';

export type Audience =
    | 'Everyone'
    | 'Admin'
    | 'HR'
    | 'Payroll'
    | 'Branch manager'
    | 'Device admin'
    | 'Employee';

export type GuideSection = {
    id: string;
    title: string;
    icon: LucideIcon;
    audience: Audience[];
    /** Extra words that should match the search box. */
    keywords: string;
    content: ReactNode;
};

export const guideSections: GuideSection[] = [
    {
        id: 'getting-started',
        title: 'Getting started',
        icon: Rocket,
        audience: ['Everyone'],
        keywords:
            'login sign in sidebar roles permissions access branch dates password',
        content: (
            <>
                <p>
                    Sign in with the email and password your administrator gave
                    you. The sidebar only shows the pages your role may use, so
                    two people can see different menus. Change your own password
                    and enable two-factor sign-in under your name at the bottom
                    of the sidebar → <Path>Settings</Path>.
                </p>
                <Sub title="Who can do what">
                    <GuideTable
                        head={['Role', 'What they can use']}
                        rows={[
                            [
                                'Super Admin',
                                'Everything, including users, company settings, payroll approval and undoing an approval.',
                            ],
                            [
                                'HR Manager',
                                'Employees (and their salaries), shifts and roster, holidays, attendance and corrections, overtime approval, leave, biometric enrolment, reports.',
                            ],
                            [
                                'Payroll Officer',
                                'Salaries and salary changes, bonuses and deductions, advances, creating payroll runs and recording payments, reports.',
                            ],
                            [
                                'Branch Manager',
                                'Their branch only: employees (view), attendance and corrections, overtime and leave approval, reports.',
                            ],
                            [
                                'Device Admin',
                                'Devices, biometric enrolment, device backups and restores.',
                            ],
                            [
                                'Employee',
                                'Only My Portal: their own attendance, leave and payslips.',
                            ],
                        ]}
                    />
                </Sub>
                <Tip>
                    An account can be tied to one branch. It then only sees that
                    branch’s employees, attendance, leave, devices and payroll.
                    Accounts without a branch see the whole company.
                </Tip>
                <Sub title="Dates and times">
                    <Bullets>
                        <li>
                            Dates are shown as day-month-year (05-10-2026). Use
                            the calendar picker to choose them.
                        </li>
                        <li>
                            Times are shown in the company’s timezone, exactly
                            as the devices recorded them.
                        </li>
                        <li>Money is in PKR with two decimals.</li>
                    </Bullets>
                </Sub>
            </>
        ),
    },
    {
        id: 'first-time-setup',
        title: 'First-time setup checklist',
        icon: ListChecks,
        audience: ['Admin', 'HR'],
        keywords: 'setup start order configure new company onboarding',
        content: (
            <>
                <p>
                    Set the app up in this order. Each step links to its
                    section.
                </p>
                <Steps>
                    <li>
                        Company name and attendance / payroll rules:{' '}
                        <See id="organisation">
                            Organisation → Company Settings
                        </See>
                        .
                    </li>
                    <li>
                        Branches, departments, designations and salary
                        components: <See id="organisation">Organisation</See>.
                    </li>
                    <li>
                        Shifts, then assign them to employees on the roster, and
                        add this year’s holidays:{' '}
                        <See id="shifts">Shifts & Roster</See>.
                    </li>
                    <li>
                        Add employees with their salary and a{' '}
                        <strong>device PIN</strong>:{' '}
                        <See id="employees">Employees</See>.
                    </li>
                    <li>
                        Connect each ZKTeco device and claim it for its branch:{' '}
                        <See id="devices">Devices</See>.
                    </li>
                    <li>
                        Enrol fingerprints/faces once and push them to every
                        device: <See id="biometrics">Biometrics</See>.
                    </li>
                    <li>
                        Create logins for HR, payroll, managers and (optionally)
                        employees: <See id="users">Users & Access</See>.
                    </li>
                    <li>
                        Turn on automatic device backups:{' '}
                        <See id="backups">Device backups</See>.
                    </li>
                </Steps>
            </>
        ),
    },
    {
        id: 'dashboard',
        title: 'Dashboard',
        icon: LayoutGrid,
        audience: ['Everyone'],
        keywords:
            'today in late absent leave chart trend needs attention devices online payroll',
        content: (
            <>
                <p>
                    The first page after signing in. What you see depends on
                    your role:
                </p>
                <Bullets>
                    <li>
                        <strong>Today</strong>: how many employees are in, late,
                        absent, on leave or off. Shifts that haven’t started yet
                        are not counted as absent.
                    </li>
                    <li>
                        <strong>Attendance, last 30 days</strong>: present,
                        late, on leave and absent per day. Hover a bar for the
                        numbers.
                    </li>
                    <li>
                        <strong>Needs attention</strong>: leave and overtime
                        waiting for approval, missing check-outs in the last 7
                        days, employees without a shift, and scans whose PIN
                        matches nobody. Click any line to go there.
                    </li>
                    <li>
                        <strong>Devices</strong>: how many are online, and new
                        devices waiting to be claimed.
                    </li>
                    <li>
                        <strong>Latest payroll</strong>: its status and total
                        net pay.
                    </li>
                </Bullets>
                <Tip>
                    Employees who only have My Portal access are taken straight
                    to their portal instead.
                </Tip>
            </>
        ),
    },
    {
        id: 'organisation',
        title: 'Organisation & company settings',
        icon: Building2,
        audience: ['Admin', 'Payroll'],
        keywords:
            'branch department designation salary component earning deduction income tax company settings policy rules late grace overtime rate day basis',
        content: (
            <>
                <Sub title="Branches, departments, designations">
                    <p>
                        Under <Path>Organisation</Path>, add and edit them in a
                        dialog. Something that employees still use can’t be
                        deleted; mark it <strong>inactive</strong> instead so it
                        disappears from forms but old records keep it.
                    </p>
                </Sub>
                <Sub title="Salary components">
                    <p>
                        The parts of a salary. <strong>Earnings</strong> (Basic
                        Salary, House Rent, Medical, Conveyance by default) add
                        up to the gross monthly salary.{' '}
                        <strong>Deductions</strong> (Income Tax by default) are
                        taken off every month at the amount set on the
                        employee’s salary. Deleting a component hides it from
                        forms; existing salaries and payslips keep it.
                    </p>
                </Sub>
                <Sub title="Company settings (Super Admin)">
                    <p>
                        <Path>Organisation → Company Settings</Path> holds the
                        company details printed on payslips and the rules used
                        to calculate attendance and pay:
                    </p>
                    <GuideTable
                        head={['Setting', 'Default', 'What it does']}
                        rows={[
                            [
                                'Late after',
                                '15 min',
                                'A check-in later than shift start + this is late.',
                            ],
                            [
                                'Early check-in window',
                                '60 min',
                                'Scans this long before a shift starts count for that shift.',
                            ],
                            [
                                'Check-out grace',
                                '120 min',
                                'Scans this long after a shift ends still count as its check-out.',
                            ],
                            [
                                'Ignore repeat scans',
                                '2 min',
                                'Scans this close together count as one.',
                            ],
                            [
                                'Half day below',
                                '240 min',
                                'Working less than this is a half day (never more than half the shift).',
                            ],
                            [
                                'Minimum overtime',
                                '30 min',
                                'Extra time below this is not overtime.',
                            ],
                            [
                                'Overtime needs approval',
                                'On',
                                'Overtime is paid only after a manager approves it.',
                            ],
                            [
                                'Per-day rate = salary ÷',
                                'Calendar days',
                                'Calendar days in the month, a fixed 30, or the employee’s scheduled working days.',
                            ],
                            [
                                'Standard hours per day',
                                '8',
                                'Hourly rate = per-day rate ÷ these hours.',
                            ],
                            [
                                'Late deduction',
                                'Every 3 lates = 1 day',
                                'None, per late minute, or every N lates deduct X days.',
                            ],
                            [
                                'Deduct early leaving',
                                'Off',
                                'Deduct leaving before shift end at the hourly rate.',
                            ],
                            [
                                'Overtime rate / holiday rate',
                                '×1.5 / ×2.0',
                                'Multipliers of the hourly rate.',
                            ],
                        ]}
                    />
                    <Tip>
                        Each shift can override the timing rules (late, early
                        window, grace, half day, minimum overtime). New settings
                        apply to attendance calculated from now on; use{' '}
                        <See id="attendance-daily">Recalculate</See> to apply
                        them to past days.
                    </Tip>
                </Sub>
            </>
        ),
    },
    {
        id: 'employees',
        title: 'Employees',
        icon: Users,
        audience: ['HR', 'Payroll', 'Branch manager'],
        keywords:
            'add employee profile salary increment revision device pin cnic bank resign terminate exit delete history',
        content: (
            <>
                <Sub title="Add an employee">
                    <Steps>
                        <li>
                            <Path>Employees → Add Employee</Path>.
                        </li>
                        <li>
                            Fill in personal details, branch, department,
                            designation, joining date and employment type.
                        </li>
                        <li>
                            Choose how they are paid (bank or cash) and add the
                            bank account for bank transfers.
                        </li>
                        <li>
                            Give them a <strong>device PIN</strong>: the user ID
                            they will have on every ZKTeco device (up to 14
                            letters or digits, unique per employee).
                        </li>
                        <li>
                            Enter the <strong>monthly salary</strong>: an amount
                            per salary component. The form shows the gross,
                            fixed deductions and net at full attendance as you
                            type.
                        </li>
                        <li>
                            Save. The salary becomes the employee’s first salary
                            record, effective from the joining date.
                        </li>
                    </Steps>
                    <Tip>
                        Leave the employee code blank to get the next number
                        automatically (EMP-0001, EMP-0002…).
                    </Tip>
                </Sub>
                <Sub title="Salary changes (increments, revisions)">
                    <p>
                        Salary is never edited in place, so history is kept. On
                        the employee’s page click{' '}
                        <strong>Add Increment / Revision</strong>, pick the
                        effective date and change the amounts. Payroll uses the
                        latest record effective on or before the end of the pay
                        period, so a raise effective on the 1st is paid from
                        that month. Future records are marked{' '}
                        <strong>Upcoming</strong>. A record already used by
                        payroll, or an employee’s only record, can’t be deleted.
                    </p>
                </Sub>
                <Sub title="When someone leaves">
                    <p>
                        Edit the employee, set the status to{' '}
                        <strong>Resigned</strong> or <strong>Terminated</strong>{' '}
                        and enter the <strong>exit date</strong> (their last
                        working day). Payroll pays them only up to that date,
                        and they are removed from every device automatically.
                        Their attendance and payslips stay. Prefer this over
                        deleting.
                    </p>
                </Sub>
                <Tip>
                    The employee list can be searched by name, code, CNIC, phone
                    or device PIN and filtered by branch, department and status.
                    Only Payroll and Super Admin see salary amounts.
                </Tip>
            </>
        ),
    },
    {
        id: 'shifts',
        title: 'Shifts, roster & holidays',
        icon: CalendarClock,
        audience: ['HR', 'Branch manager'],
        keywords:
            'shift roster assign weekdays weekly off overnight night break one day change swap day off holiday',
        content: (
            <>
                <Sub title="Shifts">
                    <p>
                        <Path>Shifts & Roster → Shifts</Path>. A shift has a
                        start, an end and an unpaid break; an end time earlier
                        than the start makes an <strong>overnight shift</strong>{' '}
                        (e.g. 22:00–06:00), which belongs to the day it starts.
                        You can override the company timing rules for one shift
                        (e.g. a stricter late limit).
                    </p>
                </Sub>
                <Sub title="Assign shifts (the roster)">
                    <Steps>
                        <li>
                            <Path>Shifts & Roster → Roster → Assign Shift</Path>
                            .
                        </li>
                        <li>
                            Pick one or many employees, the shift, and the
                            working days (no days selected = every day).
                        </li>
                        <li>
                            Set the date it starts, and optionally an end date.
                        </li>
                    </Steps>
                    <Bullets>
                        <li>
                            Days with no shift are <strong>weekly offs</strong>{' '}
                            (e.g. Monday–Saturday leaves Sunday off).
                        </li>
                        <li>
                            Someone can work several shifts (split shifts), but
                            not two that overlap in time; the app refuses and
                            names who clashes.
                        </li>
                        <li>
                            To change someone’s shift from a date, set an{' '}
                            <strong>end date</strong> on the old assignment the
                            day before and assign the new one. Deleting an
                            assignment rewrites past attendance as if it never
                            existed.
                        </li>
                        <li>
                            Employees with no assignment at all show as
                            “unscheduled”: they are never marked absent or late.
                        </li>
                    </Bullets>
                </Sub>
                <Sub title="One-day changes">
                    <p>
                        On the roster’s <strong>One-day changes</strong> tab,
                        click <strong>Change One Day</strong> to give someone a
                        different shift or a day off on one date (shift swaps,
                        compensatory off). It replaces their normal shifts for
                        that day.
                    </p>
                </Sub>
                <Sub title="Holidays">
                    <p>
                        <Path>Shifts & Roster → Holidays</Path>. Holidays are
                        paid and nobody is marked absent. Anyone who works on a
                        holiday gets all the time as holiday overtime. A holiday
                        can apply to every branch or one branch.
                    </p>
                </Sub>
                <Tip>
                    Changing the roster or holidays for past dates recalculates
                    those days automatically (except payroll-locked ones).
                </Tip>
            </>
        ),
    },
    {
        id: 'devices',
        title: 'Connecting ZKTeco devices',
        icon: Cpu,
        audience: ['Device admin'],
        keywords:
            'zkteco device adms push iclock server claim online offline reboot re-read logs users clear command unmatched scans pin',
        content: (
            <>
                <Sub title="Connect a device">
                    <Steps>
                        <li>
                            On the device: Network → Ethernet, turn DHCP on (or
                            set a fixed IP).
                        </li>
                        <li>
                            System → Device Type: choose{' '}
                            <strong>T&A Push</strong>.
                        </li>
                        <li>
                            Cloud Server Settings: enter this server’s address
                            and port (shown on the <Path>Devices</Path> page).
                            If the device has a path field use{' '}
                            <code>/iclock</code>.
                        </li>
                        <li>Save and reboot the device.</li>
                        <li>
                            Within a minute it appears in <Path>Devices</Path>{' '}
                            as <strong>Unclaimed</strong>. Click{' '}
                            <strong>Claim</strong>, name it and choose its
                            branch.
                        </li>
                    </Steps>
                    <Warning>
                        Scans from an unclaimed or disabled device are ignored.
                        Claim every device before staff start using it.
                    </Warning>
                </Sub>
                <Sub title="The device page">
                    <Bullets>
                        <li>
                            <strong>Online / Offline</strong>: a device that
                            hasn’t contacted the server for 2 minutes is
                            offline. Scans are kept on the device and uploaded
                            when it reconnects.
                        </li>
                        <li>
                            Counts of users, fingerprints, faces and logs held
                            on the device, and today’s scans.
                        </li>
                        <li>
                            <strong>Actions</strong>: re-read users &
                            biometrics, re-read attendance logs for dates
                            (missing scans are added, duplicates skipped), push
                            all branch employees, refresh device info, reboot,
                            and clear the attendance log (type CLEAR to confirm;
                            scans already received stay in the app).
                        </li>
                        <li>
                            <strong>Command log</strong>: everything sent to the
                            device and whether it succeeded. Failed commands can
                            be retried; queued ones cancelled.
                        </li>
                        <li>
                            <strong>Users on device</strong>: who the device
                            holds, matched to employees by PIN; unknown PINs can
                            be linked, and employees missing from the device are
                            flagged.
                        </li>
                    </Bullets>
                </Sub>
                <Sub title="Unmatched scans">
                    <p>
                        A scan whose PIN belongs to no employee is kept, not
                        lost. <Path>Devices → Unmatched Scans</Path> lists those
                        PINs; click <strong>Assign to employee</strong> and all
                        their earlier scans move into that employee’s
                        attendance.
                    </p>
                </Sub>
            </>
        ),
    },
    {
        id: 'biometrics',
        title: 'Fingerprints, faces & enrolment',
        icon: Fingerprint,
        audience: ['HR', 'Device admin'],
        keywords:
            'enrol enroll fingerprint face template push all devices remove biometric algorithm',
        content: (
            <>
                <p>
                    Enrol an employee once, on any device, and copy their
                    fingerprints and face to every other device.
                </p>
                <Steps>
                    <li>Make sure the employee has a device PIN.</li>
                    <li>
                        On their page, under{' '}
                        <strong>Biometrics & devices</strong>, click{' '}
                        <strong>Enrol / push to devices</strong> and tick a
                        device (or all of them).
                    </li>
                    <li>
                        At the device, the employee scans their finger or face
                        under their PIN. The device sends the template to the
                        app, where it is stored encrypted.
                    </li>
                    <li>
                        Click <strong>Enrol / push to devices</strong> again and
                        tick the other devices: they receive the user and the
                        stored templates, so the employee can scan anywhere
                        without enrolling again.
                    </li>
                </Steps>
                <Bullets>
                    <li>
                        Each device shows <strong>Queued</strong>,{' '}
                        <strong>On device</strong>, <strong>Failed</strong> or{' '}
                        <strong>Removed</strong>.
                    </li>
                    <li>
                        Templates only work on devices using the same algorithm
                        version. Incompatible ones are skipped (with a note);
                        the employee then enrols on that device too.
                    </li>
                    <li>
                        <strong>Remove</strong> deletes the employee from chosen
                        devices. This happens automatically when someone resigns
                        or is terminated.
                    </li>
                    <li>
                        A new or replaced device can receive every branch
                        employee at once: device page → Actions → Push all
                        branch employees.
                    </li>
                </Bullets>
            </>
        ),
    },
    {
        id: 'how-attendance-works',
        title: 'How attendance is calculated',
        icon: ScanLine,
        audience: ['Everyone'],
        keywords:
            'rules scan check in check out late half day absent overtime break overnight double scan forgotten checkout status weekly off unscheduled',
        content: (
            <>
                <p>
                    Every scan is stored as it arrives and never edited. Each
                    day is then calculated from the scans, the roster, holidays,
                    approved leave and managers’ corrections. It is recalculated
                    whenever any of these change and every hour, so late uploads
                    are picked up automatically.
                </p>
                <Sub title="Matching scans to shifts">
                    <Bullets>
                        <li>
                            A scan belongs to a shift when it falls in the
                            shift’s window: from the early check-in window
                            before the start to the check-out grace after the
                            end.
                        </li>
                        <li>
                            The first scan checks in; the next one checks out.
                            Scans within the repeat-scan limit (2 min) are
                            ignored.
                        </li>
                        <li>
                            A break (out and back in) makes a second session in
                            the same shift.
                        </li>
                        <li>
                            Back-to-back shifts work: scan out of one and
                            straight into the next.
                        </li>
                        <li>
                            A check-out up to 16 hours after the check-in still
                            counts, so long overtime is never lost.
                        </li>
                        <li>
                            A scan outside every shift is recorded as
                            unscheduled time (no status, no overtime on a
                            working day).
                        </li>
                    </Bullets>
                </Sub>
                <Sub title="Statuses">
                    <GuideTable
                        head={['Status', 'When']}
                        rows={[
                            ['Present', 'Checked in on time.'],
                            [
                                'Late',
                                'Checked in after start + late limit. Late minutes are counted from the shift start.',
                            ],
                            [
                                'Half day',
                                'Worked less than the half-day minimum.',
                            ],
                            [
                                'Absent',
                                'No scans once the shift window has passed.',
                            ],
                            ['Leave', 'Approved leave covers the day.'],
                            ['Holiday', 'A holiday; paid even without scans.'],
                            [
                                'Weekly off',
                                'No shift that day under the roster.',
                            ],
                            [
                                'Scheduled',
                                'Today’s shift hasn’t ended yet and no scan has arrived.',
                            ],
                            [
                                'Unscheduled',
                                'The employee has no roster, or scanned outside every shift.',
                            ],
                        ]}
                    />
                </Sub>
                <Sub title="Time worked, early leaving and overtime">
                    <Bullets>
                        <li>
                            Worked time counts from the shift start; arriving
                            early isn’t paid. An unpaid break not taken as a gap
                            is deducted.
                        </li>
                        <li>
                            Early leaving = minutes between check-out and shift
                            end.
                        </li>
                        <li>
                            Overtime = time after the shift end, if at least the
                            minimum (30 min). On holidays and weekly offs all
                            worked time is overtime, paid at the holiday rate.
                        </li>
                        <li>
                            <strong>No check-out</strong>: a check-in still open
                            16 hours later is flagged. It is credited up to the
                            shift end with no overtime until a manager fixes it.
                        </li>
                    </Bullets>
                </Sub>
            </>
        ),
    },
    {
        id: 'attendance-daily',
        title: 'Daily attendance & corrections',
        icon: CalendarCheck,
        audience: ['HR', 'Branch manager'],
        keywords:
            'daily attendance add punch missed scan discard void correct status excuse late recalculate locked details',
        content: (
            <>
                <p>
                    <Path>Attendance → Daily</Path> shows every employee’s
                    shifts for one day with their check-in, check-out, worked
                    time, late, early leaving, overtime and status. Today
                    refreshes by itself every 30 seconds. Click the status chips
                    to filter, and <strong>Details</strong> for one employee’s
                    day.
                </p>
                <Sub title="Fix a day">
                    <GuideTable
                        head={['Problem', 'What to do']}
                        rows={[
                            [
                                'Forgot to scan, or the device was down',
                                'Add Punch (or Details → Add punch) with the time and a reason. It is matched to the shift like a real scan.',
                            ],
                            [
                                'A wrong or extra scan',
                                'Details → discard the punch with a reason. It stays visible, crossed out, but is ignored.',
                            ],
                            [
                                'Worked elsewhere (client visit, training)',
                                'Details → Set status: Present, Half Day or Absent, with a reason.',
                            ],
                            [
                                'Late for an accepted reason',
                                'Details → tick “Excuse the late arrival”.',
                            ],
                            [
                                'Changed shift rules or settings',
                                'Recalculate, and pick the dates.',
                            ],
                        ]}
                    />
                    <Tip>
                        Every correction is kept with who made it and why, and
                        survives recalculation.
                    </Tip>
                    <Warning>
                        Once a payroll run that includes the day is approved,
                        the day is <strong>locked</strong> and can’t be changed.
                    </Warning>
                </Sub>
            </>
        ),
    },
    {
        id: 'attendance-register',
        title: 'Monthly register',
        icon: Table2,
        audience: ['HR', 'Payroll', 'Branch manager'],
        keywords:
            'monthly register grid month totals present absent late leave worked overtime',
        content: (
            <>
                <p>
                    <Path>Attendance → Monthly Register</Path> shows one row per
                    employee and one cell per day: <strong>P</strong> present,{' '}
                    <strong>L</strong> late, <strong>H</strong> half day,{' '}
                    <strong>A</strong> absent, <strong>LV</strong> leave,{' '}
                    <strong>HO</strong> holiday, <strong>W</strong> weekly off.
                    With several shifts in a day the worst status is shown; a
                    red ring means a missing check-out. Totals at the end show
                    worked hours, approved overtime and overtime still pending.
                </p>
            </>
        ),
    },
    {
        id: 'overtime',
        title: 'Overtime approval',
        icon: Clock,
        audience: ['HR', 'Branch manager'],
        keywords: 'overtime approve reject partial hours holiday rate',
        content: (
            <>
                <p>
                    When <em>Overtime needs approval</em> is on, overtime is
                    paid only after approval.{' '}
                    <Path>Attendance → Overtime Approval</Path> lists it per
                    employee and day.
                </p>
                <Bullets>
                    <li>
                        Approve all of it, or type fewer hours to approve part.
                        Reject approves zero.
                    </li>
                    <li>
                        Holiday and weekly-off overtime is marked so you know it
                        is paid at the higher rate.
                    </li>
                    <li>
                        Decisions can be changed until the payroll for that
                        month is approved.
                    </li>
                </Bullets>
            </>
        ),
    },
    {
        id: 'leave',
        title: 'Leave',
        icon: Plane,
        audience: ['HR', 'Branch manager', 'Employee'],
        keywords:
            'leave request apply approve reject cancel balance annual sick casual unpaid half day carry forward document',
        content: (
            <>
                <Sub title="Leave types and balances">
                    <GuideTable
                        head={[
                            'Type',
                            'Paid',
                            'Days per year',
                            'Carry forward',
                        ]}
                        rows={[
                            ['Annual Leave', 'Yes', '14', 'up to 7'],
                            ['Sick Leave', 'Yes', '8', '—'],
                            ['Casual Leave', 'Yes', '10', '—'],
                            ['Unpaid Leave', 'No', 'No limit', '—'],
                        ]}
                    />
                    <Bullets>
                        <li>
                            These are the defaults; change them under{' '}
                            <Path>Leave → Leave Types</Path> (half days,
                            document required, one gender only).
                        </li>
                        <li>
                            Someone who joins mid-year gets a share of the
                            yearly days. Balances roll over on 1 January.
                        </li>
                        <li>
                            <Path>Leave → Balances</Path> shows available /
                            total per type. Click a balance to add or remove
                            days by hand (e.g. a compensatory day).
                        </li>
                    </Bullets>
                </Sub>
                <Sub title="Requesting leave">
                    <Steps>
                        <li>
                            Employees: <Path>My Portal → Apply</Path>. HR and
                            managers: <Path>Leave → Record Leave</Path> for
                            anyone, with an option to approve it straight away.
                        </li>
                        <li>
                            Choose the type, the dates (or one date for a half
                            day), a reason and any document.
                        </li>
                    </Steps>
                    <Bullets>
                        <li>
                            Only working days are counted: weekly offs and
                            holidays inside the period are free.
                        </li>
                        <li>
                            A request can’t overlap another one, and paid leave
                            can’t exceed the available balance.
                        </li>
                    </Bullets>
                </Sub>
                <Sub title="Approving">
                    <p>
                        <Path>Leave → Requests</Path> opens on the pending ones.{' '}
                        <strong>Approve</strong> marks those days as leave in
                        attendance; <strong>Reject</strong> needs a reason. An
                        approved leave can be cancelled until payroll for those
                        days is approved. Employees can withdraw their own
                        pending requests.
                    </p>
                </Sub>
            </>
        ),
    },
    {
        id: 'adjustments',
        title: 'Bonuses, deductions & advances',
        icon: HandCoins,
        audience: ['Payroll'],
        keywords:
            'bonus allowance commission arrears fine deduction eid percent basic gross advance loan installment repayment',
        content: (
            <>
                <Sub title="Bonuses and deductions">
                    <p>
                        <Path>Payroll → Bonuses & Deductions</Path>. One-off
                        amounts for a month: bonus, allowance, commission and
                        arrears are added; fines and other deductions are taken
                        off.
                    </p>
                    <Steps>
                        <li>
                            Click Add, pick the type, a description and the
                            month.
                        </li>
                        <li>
                            Enter a fixed amount, or a percentage of basic or
                            gross salary (e.g. an Eid bonus of 50% of basic).
                        </li>
                        <li>
                            Choose selected employees, or everyone in a branch
                            and/or department.
                        </li>
                    </Steps>
                    <Tip>
                        Added after a draft payroll was created? Regenerate the
                        draft to include it.
                    </Tip>
                </Sub>
                <Sub title="Advances and loans">
                    <p>
                        <Path>Payroll → Advances & Loans</Path> → Record
                        Advance: the amount, the monthly installment and the
                        first month to recover it. Each approved payroll takes
                        the installment (never more than is left, and never
                        pushing pay below zero). Record cash repayments with{' '}
                        <strong>Repayment</strong>; the advance is settled
                        automatically when fully recovered.
                    </p>
                </Sub>
            </>
        ),
    },
    {
        id: 'payroll',
        title: 'Payroll',
        icon: Wallet,
        audience: ['Payroll', 'Admin'],
        keywords:
            'payroll run payslip salary calculate approve paid bank sheet register pdf regenerate cancel revert lock net pay',
        content: (
            <>
                <Sub title="How pay is calculated">
                    <GuideTable
                        head={['Line', 'How']}
                        rows={[
                            [
                                'Salary components',
                                'The full monthly amounts from the salary in effect at the end of the period.',
                            ],
                            [
                                'Days before joining / after leaving',
                                'Per-day rate × days not employed in the period.',
                            ],
                            ['Absent', 'Per-day rate × absent days.'],
                            ['Half days', 'Half the per-day rate × half days.'],
                            [
                                'Unpaid leave',
                                'Per-day rate × unpaid leave days.',
                            ],
                            [
                                'Late arrivals',
                                'As set in company settings: per minute, or every N lates = X days.',
                            ],
                            [
                                'Left early',
                                'Hourly rate × hours, only if switched on.',
                            ],
                            [
                                'Overtime',
                                'Approved hours × hourly rate × overtime rate (holiday/off days at the holiday rate).',
                            ],
                            [
                                'Bonuses / fines',
                                'From Bonuses & Deductions for the month.',
                            ],
                            [
                                'Income tax and other fixed deductions',
                                'The monthly amount on the salary.',
                            ],
                            [
                                'Advance installment',
                                'Taken last, only from what is left to pay.',
                            ],
                        ]}
                    />
                    <p>
                        Per-day rate = gross ÷ days (calendar days, 30, or
                        scheduled days); hourly rate = per-day rate ÷ standard
                        hours. Every line on a payslip shows quantity × rate =
                        amount.
                    </p>
                </Sub>
                <Sub title="Running payroll">
                    <Steps>
                        <li>
                            <Path>
                                Payroll → Payroll Runs → New Payroll Run
                            </Path>
                            : pick the period (last month by default) and
                            optionally one branch. Attendance is recalculated
                            and a <strong>draft</strong> payslip is built for
                            everyone on the payroll in that period. Employees
                            without a salary are skipped and named.
                        </li>
                        <li>
                            Review the run: click an employee for their full
                            payslip. Fix attendance, overtime, leave or bonuses
                            if needed and click <strong>Regenerate</strong>.
                        </li>
                        <li>
                            <strong>Approve</strong> (Super Admin): the period’s
                            attendance is locked, bonuses are marked paid and
                            advance installments are taken.
                        </li>
                        <li>
                            Download the <strong>Bank sheet</strong> (Excel) for
                            the bank transfer and the <strong>Register</strong>{' '}
                            for your records. Each payslip has a PDF.
                        </li>
                        <li>
                            Pay, then <strong>Mark all paid</strong> (or mark
                            single payslips) with the date and reference. The
                            run becomes <strong>Paid</strong> when every payslip
                            is.
                        </li>
                    </Steps>
                    <Bullets>
                        <li>
                            Two runs can’t cover the same employees and dates.
                            Cancel a draft to run the period again.
                        </li>
                        <li>
                            A Super Admin can send an approved run back to draft
                            (unlocking attendance) as long as nothing is paid
                            yet.
                        </li>
                        <li>
                            A red warning means deductions exceeded earnings for
                            someone: their net pay is zero. Check fines before
                            approving.
                        </li>
                    </Bullets>
                </Sub>
            </>
        ),
    },
    {
        id: 'backups',
        title: 'Device backups & restore',
        icon: DatabaseBackup,
        audience: ['Device admin'],
        keywords:
            'backup restore replace device broken upload download import logs automatic daily weekly retention wipe clear',
        content: (
            <>
                <Sub title="Back up">
                    <p>
                        <Path>Devices → Backups & Restore → Back Up Now</Path>.
                        Choose the device, what to include (users, fingerprints
                        and faces, attendance logs for dates) and where to read
                        it from:
                    </p>
                    <Bullets>
                        <li>
                            <strong>The device</strong>: it uploads its data
                            over a few minutes; the backup completes once it
                            goes quiet. It must be online. If it stops
                            answering, whatever arrived is kept as a partial
                            backup after 30 minutes.
                        </li>
                        <li>
                            <strong>This app</strong>: built instantly from the
                            employees, templates and scans the app already holds
                            for that device. Use this when the device is broken.
                        </li>
                    </Bullets>
                    <p>
                        Automatic backups: device page → Settings → Automatic
                        backup (daily, or weekly on Sundays, at 02:00) and how
                        many to keep. Backup files are encrypted and can only be
                        opened by this system.
                    </p>
                </Sub>
                <Sub title="Restore">
                    <Steps>
                        <li>
                            On a backup, open the menu →{' '}
                            <strong>Restore to a device…</strong>, or click{' '}
                            <strong>Restore from app</strong> to use the current
                            employees and their stored templates.
                        </li>
                        <li>
                            Pick the target device (the same one or a
                            replacement) and what to restore.
                        </li>
                        <li>
                            Optionally wipe the device first (type CLEAR). This
                            deletes its users, biometrics and logs; logs already
                            received stay in the app.
                        </li>
                        <li>Follow progress on the Restores tab.</li>
                    </Steps>
                    <Warning>
                        Attendance logs can’t be written back onto a device. Use{' '}
                        <strong>Import logs into attendance</strong> on a backup
                        to add any scans the app is missing (duplicates are
                        skipped and attendance is recalculated).
                    </Warning>
                </Sub>
                <Sub title="Replacing a broken device">
                    <Steps>
                        <li>
                            Connect the new device and claim it for the same
                            branch.
                        </li>
                        <li>
                            Restore the old device’s latest backup to it (or use
                            Restore from app).
                        </li>
                        <li>
                            If the old device’s logs were backed up after its
                            last upload, import them into attendance.
                        </li>
                    </Steps>
                </Sub>
            </>
        ),
    },
    {
        id: 'reports',
        title: 'Reports',
        icon: BarChart3,
        audience: ['HR', 'Payroll', 'Branch manager'],
        keywords:
            'report export excel attendance summary lates overtime leave taken',
        content: (
            <>
                <p>
                    <Path>Reports</Path> summarises any period per employee,
                    filtered by branch and department, with{' '}
                    <strong>Export Excel</strong>:
                </p>
                <Bullets>
                    <li>
                        <strong>Attendance summary</strong>: shifts, present,
                        late, half days, absent, leave, holidays, worked and
                        approved overtime hours, missing check-outs.
                    </li>
                    <li>
                        <strong>Lates & early leaving</strong>: counts and
                        minutes, worst first.
                    </li>
                    <li>
                        <strong>Overtime</strong>: total, approved, pending and
                        holiday/off-day hours.
                    </li>
                    <li>
                        <strong>Leave taken</strong>: approved days per leave
                        type.
                    </li>
                </Bullets>
                <Tip>
                    The payroll register and bank sheet are downloaded from each
                    payroll run.
                </Tip>
            </>
        ),
    },
    {
        id: 'users',
        title: 'Users & access',
        icon: ShieldCheck,
        audience: ['Admin'],
        keywords:
            'user account login role permission branch deactivate password employee portal',
        content: (
            <>
                <p>
                    <Path>Organisation → Users & Access</Path> (Super Admin) →
                    Add Account. Give a name, email, password, one or more
                    roles, and optionally a branch to limit them to.
                </p>
                <Bullets>
                    <li>
                        For an employee’s portal login, choose the employee
                        first (name and email fill in) and the{' '}
                        <strong>Employee</strong> role.
                    </li>
                    <li>
                        Untick “Active” to block someone from signing in; they
                        are signed out at once. Set a new password the same way.
                    </li>
                    <li>
                        You can’t deactivate yourself or remove your own Super
                        Admin role.
                    </li>
                </Bullets>
            </>
        ),
    },
    {
        id: 'my-portal',
        title: 'My Portal (for employees)',
        icon: CircleUserRound,
        audience: ['Employee'],
        keywords:
            'employee portal self service my attendance my leave my payslips download',
        content: (
            <>
                <p>Employees with a login see only their own information:</p>
                <Bullets>
                    <li>
                        <strong>My attendance</strong>: each day of the month
                        with shift, check-in, check-out, worked time and status.
                        Use the arrows to change month.
                    </li>
                    <li>
                        <strong>My leave</strong>: balances per type, requests
                        and their status. Click <strong>Apply</strong> to
                        request leave; withdraw a pending request with the undo
                        button.
                    </li>
                    <li>
                        <strong>My payslips</strong>: every approved payslip,
                        with a PDF download.
                    </li>
                </Bullets>
                <Tip>
                    If something looks wrong (a missed scan, a wrong status),
                    tell HR or your manager; they can correct it.
                </Tip>
            </>
        ),
    },
    {
        id: 'faq',
        title: 'Troubleshooting',
        icon: HelpCircle,
        audience: ['Everyone'],
        keywords:
            'problem help faq not showing missing offline wrong absent scans not appearing device',
        content: (
            <>
                <GuideTable
                    head={['Problem', 'Check']}
                    rows={[
                        [
                            'A new device doesn’t appear',
                            'Device type is T&A Push, the server address and port are right, and it was rebooted.',
                        ],
                        [
                            'Scans don’t appear in attendance',
                            'The device is claimed and enabled; the employee’s device PIN matches the PIN on the device (see Unmatched Scans).',
                        ],
                        [
                            'Someone is absent but was at work',
                            'Do they have a shift that day? Did the device upload (device page → last contact)? Re-read the logs or add the punch.',
                        ],
                        [
                            'Someone is never late or absent',
                            'They have no roster: assign a shift.',
                        ],
                        [
                            'A shift change didn’t affect old days',
                            'Use Recalculate on the daily attendance page.',
                        ],
                        [
                            'Overtime isn’t on the payslip',
                            'It needs approval: Attendance → Overtime Approval, then Regenerate the draft.',
                        ],
                        [
                            'Can’t change attendance or leave',
                            'The month’s payroll is approved, so those days are locked.',
                        ],
                        [
                            'Fingerprints don’t work on another device',
                            'The devices use different algorithm versions: enrol on that device too.',
                        ],
                    ]}
                />
            </>
        ),
    },
];
