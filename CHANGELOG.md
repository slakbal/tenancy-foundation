# Changelog

## Unreleased

- Admins have a `status` (`pending`, `active`, `inactive`) instead of an `active` flag (fresh migrate needed). Only pending and active, non soft deleted admins pass `canAccessPanel()`; a pending admin has a random password and becomes active, with the email marked verified, when the password reset link is used (`ActivateAdminOnPasswordReset`).
- Password reset enabled on both panels. Admins use their own `admin_password_reset_tokens` table (new migration) so tokens never cross between an admin and a tenant user sharing an email.
- Admin users resource (`AdminUserResource`, slug `admins`, `usingAdminUserResource()`): list, view, edit and create. Creating an admin stores a random password with status `pending`, dispatches `AdminUserCreated` and queues the `AdminInvited` email with a set password link (`notifications.admin_invited`, `null` disables it). Admins cannot delete themselves or the last active super admin. The table filters by role, status and trashed, with delete, restore and force delete (force delete is super admin only).
- Admin user actions on the table (action group) and view/edit headers: activate, deactivate (status guarded, clears the remember token; self and the last active super admin are refused), send password reset link (non pending admins), change role (`ChangeAdminUserRole`, shared with the edit form). `AdminPolicy` has `activate`, `deactivate`, `changeRole` and `sendPasswordResetLink`, all guarded by `admin_user.update` and super admin protection.
- A reset or invitation link that is invalid, expired or replaced by a newer one now sends the visitor to the request password reset page with a message, instead of showing the form first (`Filament\Pages\ResetPassword`, both panels).
- `profile()` on both panels (`EditProfile`, `usingProfilePage()` on both plugins); shared `UserNameSchema`.
- `User::isOwner()` reads the `tenant_user.is_owner` flag and drives the owner `Gate::before`, which returns `null` for non owners. New `tenant_user (tenant_id, is_owner)` index in the tenants migration (fresh migrate needed).
- The tenant profile page (General, Billing and Cancel account tabs in the app) is owner only. The seeder gives the Owner role no permission rows (it bypasses checks); re-run the seeders.

- Initial extraction: models, concerns, enums, middleware, policies, Filament components, user resources, tenancy pages, panel plugins, Filament defaults, migrations, seeders, factories, responses, install command.
