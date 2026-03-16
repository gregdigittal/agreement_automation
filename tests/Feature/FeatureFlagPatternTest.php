<?php

use App\Filament\Pages\AnalyticsDashboardPage;
use App\Models\User;
use Filament\Facades\Filament;
use Symfony\Component\Finder\Finder;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('uses Feature helper not config directly — no raw config feature calls in app source', function () {
    $finder = Finder::create()
        ->files()
        ->in(base_path('app'))
        ->name('*.php')
        ->contains("config('features.");

    $violations = [];
    foreach ($finder as $file) {
        $lines = explode("\n", $file->getContents());
        foreach ($lines as $lineNumber => $line) {
            if (str_contains($line, "config('features.")) {
                $violations[] = $file->getRelativePathname().':'.($lineNumber + 1).' — '.$line;
            }
        }
    }

    expect($violations)
        ->toBeEmpty("Found raw config('features.*') calls that should use Feature::enabled():\n".implode("\n", $violations));
});

it('advanced analytics page respects Feature helper when disabled', function () {
    config(['features.advanced_analytics' => false]);
    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);

    expect(AnalyticsDashboardPage::canAccess())->toBeFalse();
});

it('advanced analytics page respects Feature helper when enabled', function () {
    config(['features.advanced_analytics' => true]);
    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);

    expect(AnalyticsDashboardPage::canAccess())->toBeTrue();
});
