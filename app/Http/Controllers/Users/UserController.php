<?php

namespace App\Http\Controllers\Users;

use App\Concerns\FlashesToast;
use App\Concerns\PasswordValidationRules;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accounts for staff who use the app (HR, payroll, managers, device admins) and employee self-service logins.
 */
class UserController extends Controller
{
    use FlashesToast, PasswordValidationRules;

    public function index(Request $request): Response
    {
        $this->authorizeUsers($request);

        $users = User::query()
            ->with(['roles:id,name', 'branch:id,name'])
            ->when($request->input('search'), fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $employees = Employee::query()->whereIn('user_id', $users->pluck('id'))->get(['id', 'name', 'employee_code', 'user_id'])->keyBy('user_id');

        return Inertia::render('users/index', [
            'users' => $users->through(fn (User $user) => [
                ...$user->only(['id', 'name', 'email', 'branch_id', 'is_active']),
                'branch' => $user->branch?->only(['id', 'name']),
                'roles' => $user->roles->pluck('name'),
                'employee' => $employees->get($user->id)?->only(['id', 'name', 'employee_code']),
            ]),
            'roles' => RoleEnum::values(),
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name']),
            'employeesWithoutLogin' => Employee::query()->active()->whereNull('user_id')->orderBy('name')->get(['id', 'name', 'employee_code', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeUsers($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => $this->passwordRules(),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(RoleEnum::values())],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('user_id')],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'branch_id' => $data['branch_id'] ?? null,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles($data['roles']);

        if (! empty($data['employee_id'])) {
            Employee::query()->whereKey($data['employee_id'])->update(['user_id' => $user->id]);
        }

        $this->flashSuccess("Account for {$user->name} created.");

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUsers($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(RoleEnum::values())],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'is_active' => ['boolean'],
        ]);

        if ($user->is($request->user()) && (! ($data['is_active'] ?? true) || ! in_array(RoleEnum::SuperAdmin->value, $data['roles'], true) && $user->hasRole(RoleEnum::SuperAdmin->value))) {
            return back()->withErrors(['roles' => 'You cannot remove your own admin access or deactivate yourself.']);
        }

        $user->fill(collect($data)->except(['password', 'roles'])->all());

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->syncRoles($data['roles']);

        $this->flashSuccess('Account updated.');

        return back();
    }

    private function authorizeUsers(Request $request): void
    {
        abort_unless($request->user()->can(PermissionEnum::UsersManage->value), 403);
    }
}
