# Changelog

## Unreleased

- Admin panel access requires an active, non soft deleted admin; the check runs on every request.
- Password reset enabled on both panels. Admins use their own `admin_password_reset_tokens` table (new migration) so tokens never cross between an admin and a tenant user sharing an email.
- `profile()` on both panels (`EditProfile`, `usingProfilePage()` on both plugins); shared `UserNameSchema`.
- `User::isOwner()` reads the `tenant_user.is_owner` flag and drives the owner `Gate::before`, which returns `null` for non owners. New `tenant_user (tenant_id, is_owner)` index in the tenants migration (fresh migrate needed).
- The tenant profile page (General, Billing and Cancel account tabs in the app) is owner only. The seeder gives the Owner role no permission rows (it bypasses checks); re-run the seeders.

- Initial extraction: models, concerns, enums, middleware, policies, Filament components, user resources, tenancy pages, panel plugins, Filament defaults, migrations, seeders, factories, responses, install command.
