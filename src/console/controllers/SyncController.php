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
        $updates = collect($allUpdates)
            ->flatten(1)
            ->map(fn ($addon) => [
                'name' => $addon->packageName,
                'latest_version' => collect($addon->releases)->first()['version'] ?? null,
            ]);

        $info = [
            'orbit_version' => '1.0.0',
            'type' => 'craftcms',
            'has_cms_update' => count($allUpdates->cms->releases) > 0,
            'has_addons_update' => $updates
                ->filter(fn ($addon) => ! is_null($addon['latest_version']))
                ->filter(fn ($addon) => $addon['name'] !== 'craftcms/cms')
                ->isNotEmpty(),
            'app' => [
                'environment' => App::env('CRAFT_ENVIRONMENT') ?? App::env('ENVIRONMENT'),
                'app_name' => Craft::$app->getSystemName(),
                'url' => UrlHelper::siteUrl(),
                'admin_url' => UrlHelper::cpUrl(),
                'yii_version' => Craft::getVersion(),
                'craft_version' => Craft::$app->getVersion().' '.App::editionName(Craft::$app->getEdition()),
                'php_version' => App::phpVersion(),
                'composer_version' => 'N/A',
                'dev_mode' => App::devMode(),
                'offline_mode' => ! Craft::$app->getIsLive(),
                'ray_enabled' => App::parseBooleanEnv('$RAY_ENABLED'),
            ],
            'drivers' => [],
            'addons' => collect($addons)->map(function ($addon) use ($updates) {
                return [
                    'name' => $addon->name,
                    'package' => $addon->packageName,
                    'version' => $addon->version,
                    'latest_version' => $updates->firstWhere('name', $addon->packageName)['latest_version'] ?? null,
                ];
            })->sortBy('name')
			->prepend([
				'name' => "Craft CMS",
				'package' => "craftcms/cms",
				'version' => Craft::$app->getVersion(),
				'latest_version' => $allUpdates->cms->releases[0]->version ?? null,
			])->values()->toArray(),
        ];

        try {
            $client = new Client([
                'base_uri' => 'https://orbit.trendyminds.com',
                'headers' => ['Accept' => 'application/json'],
            ]);

            $client->post('/api/transmit', [
                'json' => ['key' => App::env('ORBIT_KEY'), 'info' => $info],
            ]);
            $this->stdout('Data sent to Orbit.');
        } catch (\Exception $e) {
            $this->stderr('Failed to send data to Orbit.');
            Craft::error($e->getMessage());
        }

        return ExitCode::OK;
    }
}
