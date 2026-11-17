<?php

namespace Slakbal\TenancyFoundation\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Slakbal\TenancyFoundation\Actions\Admin\CreateAdminUser;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\PermissionRegistrar;

class CreateAdminCommand extends Command
{
    protected $signature = 'tenancy-foundation:create-admin
        {email : Email address of the new admin}
        {--role=super_admin : Admin role to assign}';

    protected $description = 'Create a pending admin and email the invitation';

    public function handle(CreateAdminUser $createAdminUser, PermissionRegistrar $registrar): int
    {
        $data = [
            'email' => $this->argument('email'),
            'role' => $this->option('role'),
            ...$this->askNameAttributes(),
        ];

        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $registrar->setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

        if (! $registrar->getRoleClass()::query()->where('name', $data['role'])->where('guard_name', AuthGuard::ADMIN->value)->exists()) {
            $this->components->error("The role {$data['role']} does not exist yet. Seed the roles first (AdminRolePermissionsSeeder).");

            return self::FAILURE;
        }

        $admin = $createAdminUser->handle($data, null);

        $this->components->info("Admin {$admin->email} created as {$data['role']}. The invitation is emailed when notifications.admin_invited is set.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string|null>
     */
    protected function askNameAttributes(): array
    {
        return collect(config('tenancy-foundation.user_name_attributes'))
            ->mapWithKeys(fn (string $attribute): array => [$attribute => $this->ask(str($attribute)->headline()->toString())])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $adminModel = config('tenancy-foundation.models.admin');

        return [
            'email' => ['required', 'email', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($adminModel): void {
                if ($adminModel::withTrashed()->where('email', $value)->exists()) {
                    $fail('An admin with this email already exists (it may be deleted).');
                }
            }],
            'role' => ['required', Rule::enum(AdminRoles::class)],
            ...collect(config('tenancy-foundation.user_name_attributes'))
                ->mapWithKeys(fn (string $attribute): array => [$attribute => ['required', 'string', 'max:255']])
                ->all(),
        ];
    }
}
