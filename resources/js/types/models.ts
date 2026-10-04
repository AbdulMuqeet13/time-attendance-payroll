export type Branch = {
    employees_count?: number;
    id: number;
    name: string;
    code: string;
    address: string | null;
    phone: string | null;
    is_active: boolean;
};

export type Department = {
    id: number;
    name: string;
    is_active: boolean;
};

export type Designation = {
    id: number;
    name: string;
    is_active: boolean;
};

export type Option = {
    id: number;
    name: string;
};

export type NamedRecord = {
    id: number;
    name: string;
    is_active: boolean;
    employees_count?: number;
};

export type SalaryComponentType = 'earning' | 'deduction';

export type SalaryComponent = {
    id: number;
    name: string;
    type: SalaryComponentType;
    sort_order: number;
    is_active: boolean;
};

export type EmploymentStatus =
    | 'active'
    | 'suspended'
    | 'resigned'
    | 'terminated';

export type Employee = {
    id: number;
    employee_code: string;
    name: string;
    father_name: string | null;
    cnic: string | null;
    gender: 'male' | 'female' | 'other';
    date_of_birth: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    branch_id: number;
    department_id: number | null;
    designation_id: number | null;
    reports_to_id: number | null;
    employment_type: 'permanent' | 'contract' | 'probation' | 'intern';
    status: EmploymentStatus;
    joining_date: string;
    confirmation_date: string | null;
    exit_date: string | null;
    payment_method: 'bank' | 'cash';
    bank_name: string | null;
    account_title: string | null;
    account_number: string | null;
    device_pin: string | null;
    user_id: number | null;
    branch?: Option;
    department?: Option | null;
    designation?: Option | null;
    manager?: Pick<Employee, 'id' | 'name' | 'employee_code'> | null;
    user?: { id: number; email: string } | null;
    current_salary?: Pick<EmployeeSalary, 'id' | 'gross_salary'> | null;
};

export type EmployeeOption = Pick<Employee, 'id' | 'name' | 'employee_code'>;

export type SalaryChangeType =
    | 'initial'
    | 'increment'
    | 'decrement'
    | 'revision';

export type EmployeeSalaryComponent = {
    id: number;
    salary_component_id: number;
    amount: string;
    salary_component: Pick<SalaryComponent, 'id' | 'name' | 'type'>;
};

export type EmployeeSalary = {
    id: number;
    employee_id: number;
    effective_date: string;
    change_type: SalaryChangeType;
    gross_salary: string;
    fixed_deductions: string;
    remarks: string | null;
    created_at: string;
    creator?: Option | null;
    components: EmployeeSalaryComponent[];
};

export type ShiftColor =
    | 'sky'
    | 'emerald'
    | 'amber'
    | 'violet'
    | 'rose'
    | 'slate';

export type Shift = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    break_minutes: number;
    late_grace_minutes: number | null;
    early_window_minutes: number | null;
    checkout_grace_minutes: number | null;
    half_day_minutes: number | null;
    min_overtime_minutes: number | null;
    color: ShiftColor;
    is_active: boolean;
    scheduled_minutes?: number;
    is_overnight?: boolean;
    employees_count?: number;
};

export type ShiftOption = Pick<
    Shift,
    'id' | 'name' | 'start_time' | 'end_time'
>;

export type ShiftAssignment = {
    id: number;
    employee_id: number;
    shift_id: number;
    days: number[] | null;
    effective_from: string;
    effective_to: string | null;
    employee: EmployeeOption & { branch_id: number; branch?: Option };
    shift: Shift;
};

export type RosterOverride = {
    id: number;
    employee_id: number;
    date: string;
    shift_id: number | null;
    note: string | null;
    employee: EmployeeOption;
    shift: Shift | null;
};

export type Holiday = {
    id: number;
    date: string;
    name: string;
    branch_id: number | null;
    branch?: Option | null;
};

export type Device = {
    id: number;
    serial_number: string;
    name: string;
    branch_id: number | null;
    branch?: Option | null;
    is_active: boolean;
    is_online: boolean;
    uses_push_v3?: boolean;
    model: string | null;
    firmware: string | null;
    push_version: string | null;
    ip_address: string | null;
    last_seen_at: string | null;
    user_count: number | null;
    fp_count: number | null;
    face_count: number | null;
    att_count: number | null;
    fp_algorithm: string | null;
    face_algorithm: string | null;
    auto_backup: 'none' | 'daily' | 'weekly';
    backup_retention: number;
    open_commands_count?: number;
};

export type DeviceCommandStatus =
    | 'pending'
    | 'sent'
    | 'succeeded'
    | 'failed'
    | 'cancelled';

export type DeviceCommand = {
    id: number;
    sequence: number;
    type: string;
    command: string;
    status: DeviceCommandStatus;
    attempts: number;
    sent_at: string | null;
    executed_at: string | null;
    return_code: number | null;
    created_at: string;
};

export type UnmatchedPin = {
    pin: string;
    punches_count: number;
    first_punch_at: string;
    last_punch_at: string;
};

export type BiometricTemplate = {
    id: number;
    type: 'fingerprint' | 'face' | 'palm' | 'other';
    finger_index: number;
    size: number | null;
    storage: 'fingertmp' | 'biodata';
    major_version: string | null;
    captured_at: string;
    source_device?: Option | null;
};

export type EnrollmentStatus =
    | 'queued'
    | 'on_device'
    | 'removing'
    | 'removed'
    | 'failed';

export type DeviceEnrollment = {
    id: number;
    device_id: number;
    status: EnrollmentStatus;
    message: string | null;
    synced_at: string | null;
    device: Pick<Device, 'id' | 'name' | 'serial_number'>;
};

export type EmployeeBiometrics = {
    templates: BiometricTemplate[];
    enrollments: DeviceEnrollment[];
    devices: Pick<Device, 'id' | 'name' | 'serial_number' | 'last_seen_at'>[];
};

export type DeviceUserRow = {
    pin: string;
    device_name: string | null;
    fingerprint_count: number;
    has_face: boolean;
    privilege: number;
    employee: (EmployeeOption & { status: EmploymentStatus }) | null;
    state: 'linked' | 'unknown' | 'missing';
};

export type AttendanceStatus =
    | 'present'
    | 'late'
    | 'half_day'
    | 'absent'
    | 'leave'
    | 'holiday'
    | 'weekly_off'
    | 'scheduled'
    | 'unscheduled';

export type AttendanceSession = {
    id: number;
    check_in: string;
    check_out: string | null;
    minutes: number;
};

export type AttendanceDay = {
    id: number;
    employee_id: number;
    date: string;
    shift_id: number | null;
    day_type: 'working' | 'weekly_off' | 'holiday';
    status: AttendanceStatus;
    scheduled_start: string | null;
    scheduled_end: string | null;
    scheduled_minutes: number;
    first_in: string | null;
    last_out: string | null;
    worked_minutes: number;
    late_minutes: number;
    early_leave_minutes: number;
    overtime_minutes: number;
    approved_overtime_minutes: number | null;
    is_missing_checkout: boolean;
    leave_fraction: string;
    is_overridden: boolean;
    note: string | null;
    locked_at: string | null;
    employee: EmployeeOption & { department?: Option | null };
    shift: Shift | null;
    sessions?: AttendanceSession[];
    leave_request?: { id: number; leave_type?: Option } | null;
};

export type AttendancePunch = {
    id: number;
    punched_at: string;
    verify_type: number | null;
    source: 'device' | 'manual' | 'import' | 'restore';
    reason: string | null;
    voided_at: string | null;
    void_reason: string | null;
    device?: Option | null;
    creator?: Option | null;
};

export type LeaveType = {
    id: number;
    name: string;
    code: string;
    is_paid: boolean;
    yearly_quota?: string;
    carry_forward_max?: string;
    allow_half_day: boolean;
    requires_attachment: boolean;
    gender?: string | null;
    is_active?: boolean;
};

export type LeaveStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export type LeaveRequest = {
    id: number;
    employee_id: number;
    start_date: string;
    end_date: string;
    is_half_day: boolean;
    days: string;
    reason: string | null;
    attachment_path: string | null;
    status: LeaveStatus;
    decided_at: string | null;
    decision_note: string | null;
    created_at: string;
    employee: EmployeeOption;
    leave_type: Pick<LeaveType, 'id' | 'name' | 'code' | 'is_paid'>;
    requester?: Option | null;
    decider?: Option | null;
};

export type LeaveBalanceSummary = {
    total: number;
    used: number;
    pending: number;
    available: number;
    balance_id: number;
};

export type PayrollStatus = 'draft' | 'approved' | 'paid' | 'cancelled';

export type PayrollRun = {
    id: number;
    reference: string;
    period_start: string;
    period_end: string;
    branch_id: number | null;
    branch?: Option | null;
    status: PayrollStatus;
    employee_count: number;
    earnings_total: string;
    deductions_total: string;
    net_total: string;
    notes: string | null;
    approved_at: string | null;
    paid_at: string | null;
    created_at: string;
    creator?: Option | null;
    approver?: Option | null;
};

export type PayslipItem = {
    id: number;
    side: 'earning' | 'deduction';
    category: string;
    name: string;
    quantity: string | null;
    unit: string | null;
    rate: string | null;
    amount: string;
};

export type Payslip = {
    id: number;
    employee_id: number;
    employee_code: string;
    employee_name: string;
    department: string | null;
    designation: string | null;
    payment_method: 'bank' | 'cash';
    bank_name: string | null;
    account_number: string | null;
    monthly_gross: string;
    per_day_rate: string;
    per_hour_rate: string;
    period_days: number;
    employed_days: number;
    present_days: string;
    absent_days: string;
    half_days: string;
    paid_leave_days: string;
    unpaid_leave_days: string;
    holidays: number;
    weekly_offs: number;
    late_count: number;
    late_minutes: number;
    overtime_minutes: number;
    holiday_overtime_minutes: number;
    earnings_total: string;
    deductions_total: string;
    net_pay: string;
    shortfall: string;
    payment_status: 'unpaid' | 'paid';
    paid_at: string | null;
    payment_reference: string | null;
    items: PayslipItem[];
};

export type AdjustmentKind =
    | 'bonus'
    | 'allowance'
    | 'commission'
    | 'arrears'
    | 'fine'
    | 'deduction';

export type PayrollAdjustment = {
    id: number;
    employee_id: number;
    period: string;
    kind: AdjustmentKind;
    name: string;
    amount: string;
    notes: string | null;
    payroll_run_id: number | null;
    employee: EmployeeOption;
    payroll_run?: { id: number; reference: string } | null;
};

export type SalaryAdvance = {
    id: number;
    employee_id: number;
    amount: string;
    issued_on: string;
    installment_amount: string;
    start_period: string;
    status: 'active' | 'settled' | 'cancelled';
    notes: string | null;
    remaining: string;
    employee: EmployeeOption;
    recoveries: {
        id: number;
        amount: string;
        recovered_on: string;
        note: string | null;
        payslip?: {
            payroll_run?: { reference: string; status: PayrollStatus };
        } | null;
    }[];
};

export type DeviceBackup = {
    id: number;
    device_id: number | null;
    device_serial: string;
    device_name: string;
    mode: 'device_query' | 'server_snapshot' | 'uploaded';
    include_users: boolean;
    include_templates: boolean;
    include_logs: boolean;
    logs_from: string | null;
    logs_to: string | null;
    status: 'collecting' | 'completed' | 'partial' | 'failed';
    started_at: string;
    completed_at: string | null;
    counts: {
        users: number;
        fingerprints: number;
        faces: number;
        other_templates: number;
        logs: number;
    } | null;
    file_size: number | null;
    fp_algorithm: string | null;
    face_algorithm: string | null;
    error: string | null;
    created_at: string;
    device?: Option | null;
    creator?: Option | null;
};

export type DeviceRestore = {
    id: number;
    target_device_id: number;
    status: 'running' | 'completed' | 'partial' | 'failed';
    clear_first: boolean;
    users_count: number;
    templates_count: number;
    skipped_templates: number;
    total_commands: number;
    succeeded_commands: number;
    failed_commands: number;
    created_at: string;
    completed_at: string | null;
    target_device: Option;
    backup?: { id: number; device_name: string; created_at: string } | null;
    creator?: Option | null;
};

export type BackupDevice = Pick<
    Device,
    | 'id'
    | 'name'
    | 'serial_number'
    | 'last_seen_at'
    | 'fp_algorithm'
    | 'face_algorithm'
    | 'push_version'
    | 'is_active'
>;
