<?php

namespace Slakbal\TenancyFoundation\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Slakbal\TenancyFoundation\Actions\Admin\CreateAdminUser;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\PermissionRegistrar;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'tenancy-foundation:create-admin
        {email : Email address of the new admin}
        {--role= : Admin role to assign (asked when omitted, super_admin without interaction)}';

    protected $description = 'Create a pending admin and email the invitation';

    public function handle(CreateAdminUser $createAdminUser, PermissionRegistrar $registrar): int
    {
        $registrar->setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

        $roles = $this->adminRoles($registrar);

        if ($roles === []) {
            $this->components->error('No admin roles exist yet. Seed the roles first (AdminRolePermissionsSeeder).');

            return self::FAILURE;
        }

        $data = [
            'email' => $this->argument('email'),
            ...$this->askNameAttributes(),
            'role' => $this->option('role') ?? $this->askRole($roles),
        ];

        $validator = Validator::make($data, $this->rules(array_keys($roles)), [
            'role.in' => 'Unknown role. Available roles: '.implode(', ', array_keys($roles)).'.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = $createAdminUser->handle($data, null);

        $this->components->info("Admin {$admin->email} created as {$data['role']}. The invitation is emailed when notifications.admin_invited is set.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, Closure>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'email' => fn (): string => text(
                label: 'Email address of the new admin',
                placeholder: 'admin@example.com',
                required: true,
                validate: fn (string $value): ?string => $this->validationError('email', $value, $this->emailRules()),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function askNameAttributes(): array
    {
        return collect(config('tenancy-foundation.user_name_attributes'))
            ->mapWithKeys(fn (string $attribute): array => [$attribute => text(
                label: str($attribute)->headline()->toString(),
                required: true,
                validate: fn (string $value): ?string => $this->validationError($attribute, $value, ['string', 'max:255']),
            )])
            ->all();
    }

    /**
     * @param  array<string, string>  $roles
     */
    protected function askRole(array $roles): string
    {
        $default = array_key_exists(AdminRoles::SUPER_ADMIN->value, $roles) ? AdminRoles::SUPER_ADMIN->value : (string) array_key_first($roles);

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return (string) select(label: 'Role', options: $roles, default: $default);
    }

    /**
     * @return array<string, string>
     */
    protected function adminRoles(PermissionRegistrar $registrar): array
    {
        return $registrar->getRoleClass()::query()
            ->where('guard_name', AuthGuard::ADMIN->value)
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => AdminRoles::tryFrom($name)?->getLabel() ?? str($name)->headline()->toString()])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    protected function validationError(string $attribute, string $value, array $rules): ?string
    {
        return Validator::make([$attribute => $value], [$attribute => $rules])->errors()->first($attribute) ?: null;
    }

    /**
     * @return array<int, mixed>
     */
    protected function emailRules(): array
    {
        $adminModel = config('tenancy-foundation.models.admin');

        return ['required', 'email', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($adminModel): void {
            if ($adminModel::withTrashed()->where('email', $value)->exists()) {
                $fail('An admin with this email already exists (it may be deleted).');
            }
        }];
    }

    /**
     * @param  array<int, string>  $roleNames
     * @return array<string, mixed>
     */
    protected function rules(array $roleNames): array
    {
        return [
            'email' => $this->emailRules(),
            'role' => ['required', Rule::in($roleNames)],
            ...collect(config('tenancy-foundation.user_name_attributes'))
                ->mapWithKeys(fn (string $attribute): array => [$attribute => ['required', 'string', 'max:255']])
                ->all(),
        ];
    }
}
