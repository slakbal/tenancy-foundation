<?php

namespace Slakbal\TenancyFoundation\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

class AccountWidget extends StatsOverviewWidget implements HasActions
{
    use InteractsWithActions;

    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return Filament::auth()->check();
    }

    public function content(Schema $schema): Schema
    {
        $user = Filament::auth()->user();

        return $schema->components([
            Section::make()->compact()->gap(false)->schema([
                Flex::make([
                    Image::make(Filament::getUserAvatarUrl($user), Filament::getUserName($user))
                        ->imageSize('3rem')
                        ->extraAttributes(['style' => 'border-radius: 9999px; border-width: 0;'])
                        ->grow(false),
                    Group::make([
                        Text::make(__('filament-panels::widgets/account-widget.welcome', ['app' => config('app.name')]))
                            ->weight(FontWeight::SemiBold)
                            ->size(TextSize::Large),
                        Flex::make([
                            Text::make(Filament::getUserName($user))->color('gray')->grow(false),
                            ...$this->roleBadges($user),
                        ])->dense()->verticalAlignment(VerticalAlignment::Center),
                    ]),
                    Group::make([
                        Actions::make([$this->logoutAction()]),
                    ])->grow(false),
                ])->dense()->verticalAlignment(VerticalAlignment::Center),
            ]),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    protected function roleBadges(Authenticatable $user): array
    {
        $enum = $user instanceof (config('tenancy-foundation.models.admin')) ? AdminRoles::class : TenantRoles::class;

        return $user->roles->pluck('name')
            ->map(fn (string $name): Component => Text::make($enum::tryFrom($name)?->getLabel() ?? Str::headline($name))
                ->badge()
                ->color($enum::tryFrom($name)?->getColor() ?? 'gray')
                ->icon($enum::tryFrom($name)?->getIcon())
                ->grow(false))
            ->all();
    }

    protected function logoutAction(): Action
    {
        return Action::make('logout')
            ->label(__('filament-panels::widgets/account-widget.actions.logout.label'))
            ->icon(Heroicon::ArrowLeftEndOnRectangle)
            ->color('gray')
            ->url(Filament::getLogoutUrl())
            ->postToUrl();
    }
}
