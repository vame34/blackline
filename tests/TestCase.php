<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $storage = (string) env('LARAVEL_STORAGE_PATH', '');
        if ($storage === '') {
            $storage = sys_get_temp_dir().'/laravel-storage-test';
        }

        foreach (['framework/cache', 'framework/views', 'framework/testing', 'logs'] as $path) {
            $dir = rtrim($storage, '/').'/'.$path;
            if (! is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }

        if (! config('view.compiled')) {
            config()->set('view.compiled', rtrim($storage, '/').'/framework/views');
        }

        $servicesCache = (string) env('APP_SERVICES_CACHE', '');
        if ($servicesCache !== '' && is_file($servicesCache)) {
            @unlink($servicesCache);
        }

        $packagesCache = (string) env('APP_PACKAGES_CACHE', '');
        if ($packagesCache !== '' && is_file($packagesCache)) {
            @unlink($packagesCache);
        }
    }
}
