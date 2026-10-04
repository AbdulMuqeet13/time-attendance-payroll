<?php

namespace App\Http\Controllers\Organisation;

use App\Concerns\FlashesToast;
use App\Enums\DayBasis;
use App\Enums\LatePolicy;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\UpdateCompanySettingsRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company details and the attendance / payroll policies.
 */
class CompanySettingsController extends Controller
{
    use FlashesToast;

    public function edit(Request $request, Settings $settings): Response
    {
        abort_unless($request->user()->can(PermissionEnum::SettingsManage->value), 403);

        return Inertia::render('organisation/settings', [
            'settings' => collect($settings->all())
                ->mapWithKeys(fn (mixed $value, string $key) => [str_replace('.', '__', $key) => $value]),
            'dayBases' => DayBasis::options(),
            'latePolicies' => LatePolicy::options(),
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request, Settings $settings): RedirectResponse
    {
        $settings->set($request->settings());

        $this->flashSuccess('Settings saved. They apply to attendance processed from now on.');

        return to_route('company-settings.edit');
    }
}
