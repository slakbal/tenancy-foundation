<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

/**
 * Stands in for an application's own tenant permission enum.
 */
enum AppPermissions: string
{
    case INVOICE_VIEW_ANY = 'invoice.view_any';
    case INVOICE_APPROVE = 'invoice.approve';
    case PURCHASE_ORDER_EXPORT = 'purchase_order.export_all';
}
