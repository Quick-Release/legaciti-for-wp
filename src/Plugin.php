<?php

declare(strict_types=1);

namespace LegacitiForWp;

use LegacitiForWp\Admin\SettingsPage;
use LegacitiForWp\Api\Client;
use LegacitiForWp\Api\SyncService;
use LegacitiForWp\Database\PersonRepository;
use LegacitiForWp\Database\PublicationRepository;
use LegacitiForWp\Database\RelationRepository;
use LegacitiForWp\Database\TableManager;
use LegacitiForWp\RestApi\DashboardController;
use LegacitiForWp\RestApi\PeopleController;
use LegacitiForWp\RestApi\PublicationsController;
use LegacitiForWp\RestApi\SettingsController;
use LegacitiForWp\Routing\Router;
use LegacitiForWp\Scheduling\CronManager;

final class Plugin
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function init(): void
    {
        if (self::$instance !== null) {
            return;
        }

        self::$instance = new self();
        self::$instance->boot();
    }

    private function boot(): void
    {
        $tableManager = new TableManager();
        $personRepo = new PersonRepository();
        $publicationRepo = new PublicationRepository();
        $relationRepo = new RelationRepository();

        $activation = new Activation($tableManager);
        $deactivation = new Deactivation();

        register_activation_hook(LEGACITI_PLUGIN_FILE, [$activation, 'activate']);
        register_deactivation_hook(LEGACITI_PLUGIN_FILE, [$deactivation, 'deactivate']);

        $client = new Client();
        $syncService = new SyncService($client, $personRepo, $publicationRepo, $relationRepo);
        $cronManager = new CronManager($syncService);

        $router = new Router($personRepo, $publicationRepo);

        $settingsPage = new SettingsPage();
        $peopleController = new PeopleController($personRepo, $publicationRepo, $relationRepo);
        $publicationsController = new PublicationsController($publicationRepo, $personRepo, $relationRepo);
        $dashboardController = new DashboardController($personRepo, $publicationRepo);
        $settingsController = new SettingsController($syncService);

        add_action('plugins_loaded', [$cronManager, 'register']);
        add_action('plugins_loaded', [$settingsPage, 'register']);
        add_action('plugins_loaded', [$router, 'register']);
        add_action('rest_api_init', [$peopleController, 'registerRoutes']);
        add_action('rest_api_init', [$publicationsController, 'registerRoutes']);
        add_action('rest_api_init', [$dashboardController, 'registerRoutes']);
        add_action('rest_api_init', [$settingsController, 'registerRoutes']);
    }
}
