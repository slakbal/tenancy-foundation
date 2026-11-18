<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

/**
 * Stands in for an application's own admin permission enum.
 */
enum AppAdminPermissions: string
{
    case AUDIT_LOG_VIEW_ANY = 'audit_log.view_any';
    case AUDIT_LOG_EXPORT_CSV = 'audit_log.export_csv';
}
