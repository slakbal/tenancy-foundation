<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Slakbal\TenancyFoundation\Enums\ContactStatus;

class ContactRequestStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $days = (int) config('tenancy-foundation.contact_requests.prune_after_days');

        $counts = config('tenancy-foundation.models.contact_request')::query()
            ->when($days > 0, fn ($query) => $query->where('created_at', '>=', now()->subDays($days)->startOfDay()))
            ->toBase()
            ->selectRaw('count(case when status = ? then 1 end) as new_count', [ContactStatus::NEW->value])
            ->selectRaw('count(responded_at) as responded_count')
            ->selectRaw('count(case when status = ? then 1 end) as dismissed_count', [ContactStatus::DISMISSED->value])
            ->first();

        $period = $days > 0 ? __('Last :days days', ['days' => $days]) : __('All time');

        return [
            Stat::make(ContactStatus::NEW->getLabel(), (int) $counts->new_count)
                ->description($period)
                ->descriptionIcon(ContactStatus::NEW->getIcon())
                ->color(ContactStatus::NEW->getColor()),
            Stat::make(__('Responded'), (int) $counts->responded_count)
                ->description($period)
                ->descriptionIcon(Heroicon::OutlinedPaperAirplane)
                ->color(ContactStatus::COMPLETED->getColor()),
            Stat::make(ContactStatus::DISMISSED->getLabel(), (int) $counts->dismissed_count)
                ->description($period)
                ->descriptionIcon(ContactStatus::DISMISSED->getIcon())
                ->color(ContactStatus::DISMISSED->getColor()),
        ];
    }
}
