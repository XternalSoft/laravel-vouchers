<?php

namespace MOIREI\Vouchers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class VouchersServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../translations', 'vouchers');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('vouchers.php'),
            ], 'vouchers-config');


            $this->publishes([
                __DIR__ . '/../database/migrations/0000_00_00_000000_create_vouchers_table.php' => $this->getMigrationFileName(
                    'create_vouchers_table.php'
                ),
            ], 'vouchers-migrations');

            $this->publishes([
                __DIR__ . '/../translations' => resource_path('lang/vendor/vouchers'),
            ], 'vouchers-translations');
        }

        $voucherClass = config('vouchers.models.vouchers');
        $voucherObserver = config('vouchers.models.voucher_observer');
        $voucherClass::observe(new $voucherObserver);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'vouchers');

        $this->app->singleton('vouchers', function ($app) {
            $generator = new VoucherGenerator(config('vouchers.codes.characters'), config('vouchers.codes.mask'));
            $generator->setPrefix(config('vouchers.codes.prefix'));
            $generator->setSuffix(config('vouchers.codes.suffix'));
            $generator->setSeparator(config('vouchers.codes.separator'));
            return new Vouchers($generator);
        });
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @return string
     */
    protected function getMigrationFileName($migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make($this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $migrationFileName) {
                return $filesystem->glob($path . '*_' . $migrationFileName);
            })
            ->push($this->app->databasePath() . "/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }
}
