<?php

namespace trendyminds\orbit\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use GuzzleHttp\Client;
use yii\console\ExitCode;

class SyncController extends Controller
{
    /**
     * Sync application data to Orbit
     */
    public function actionIndex(): int
    {
        $addons = Craft::$app->getPlugins()->getAllPlugins();
        $allUpdates = Craft::$app->getUpdates()->getUpdates();

        $hasCriticalUpdates = collect($allUpdates)
            ->flatten(1)
            ->map(fn ($addon) => $addon->releases ?? null)
            ->flatten(1)
            ->filter()
            ->values()
            ->filter(fn ($release) => $release->critical)
            ->isNotEmpty();

        $updates = collect($allUpdates)
            ->flatten(1)
            ->map(fn ($addon) => [
                'name' => $addon->packageName,
                'latest_version' => collect($addon->releases)->first()['version'] ?? null,
            ]);

        $data = [
            'url' => UrlHelper::siteUrl(),
            'admin_url' => UrlHelper::cpUrl(),
            'php_version' => App::phpVersion(),
            'composer_version' => 'N/A',
            'debug_mode' => App::devMode(),
            'maintenance_mode' => ! Craft::$app->getIsLive(),
            'ray_enabled' => (bool) App::parseBooleanEnv('$RAY_ENABLED'),
            'platform' => 'Yii',
            'platform_version' => Craft::getVersion(),
            'cms' => 'Craft CMS',
            'cms_version' => Craft::$app->getVersion().' '.App::editionName(Craft::$app->getEdition()),
            'has_critical_updates' => $hasCriticalUpdates,
            'static_caching' => null,
            'stache_watcher' => null,
            'addons' => collect($addons)->map(function ($addon) use ($updates) {
                return [
                    'name' => $addon->name,
                    'package' => $addon->packageName,
                    'version' => $addon->version,
                    'latest_version' => $updates->firstWhere('name', $addon->packageName)['latest_version'] ?? null,
                ];
            })
                ->sortBy('name')
                ->prepend([
                    'name' => 'Craft CMS',
                    'package' => 'craftcms/cms',
                    'version' => Craft::$app->getVersion(),
                    'latest_version' => $allUpdates->cms->releases[0]->version ?? null,
                ])
                ->values()
                ->toArray(),
        ];

        try {
            $client = new Client([
                'base_uri' => 'https://orbit.trendyminds.com',
                'headers' => ['Accept' => 'application/json'],
            ]);

            $client->post('/api/transmit', [
                'json' => [
                    'key' => App::env('ORBIT_KEY'),
                    ...$data,
                ],
            ]);
            $this->stdout('Data sent to Orbit.');
        } catch (\Exception $e) {
            $this->stderr('Failed to send data to Orbit.');
            Craft::error($e->getMessage());
        }

        return ExitCode::OK;
    }
}
