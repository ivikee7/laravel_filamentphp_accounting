<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Reports\BalanceSheetReport;
use App\Filament\Pages\Reports\CashFlowReport;
use App\Filament\Pages\Reports\ProfitAndLossReport;
use App\Filament\Pages\Reports\TrialBalanceReport;
use App\Filament\Pages\Tenancy\EditTeamProfile;
use App\Filament\Pages\Tenancy\RegisterTeam;
use App\Filament\Pages\Tenancy\TeamMembers;
use App\Filament\Pages\User\EditUser;
use App\Models\Team;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                TrialBalanceReport::class,
                ProfitAndLossReport::class,
                BalanceSheetReport::class,
                CashFlowReport::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
//                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->passwordReset() // Password Reset
            ->profile() // Profile
            ->tenant(Team::class)
            ->tenantRegistration(RegisterTeam::class)
            ->tenantProfile(EditTeamProfile::class)
            ->spa()
            ->tenantMenuItems([
                [
                    Action::make('Team Members')
                        ->url(fn() => TeamMembers::getUrl())
                        ->icon('heroicon-m-users')
                        ->visible(fn() => auth()->user()?->isSuperUser()),
                ],
                [
                    Action::make('documentation')
                        ->url('https://filamentphp.com/docs/5.x')
                        ->openUrlInNewTab()
                        ->icon('heroicon-m-book-open')
                        ->visible(fn() => auth()->user()?->isSuperUser()), // Checks if user is superuser,
                ],
            ])
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
            ->spa()
            ->maxContentWidth(Width::Full)
            ->sidebarFullyCollapsibleOnDesktop()
            ->bootUsing(function () {
                Table::configureUsing(function (Table $table): void {
                    $table->paginated([5, 10, 25, 50])
                        ->defaultPaginationPageOption(5);
                });
            });
    }
}
