<?php

declare(strict_types=1);

namespace LegacitiForWp\Routing;

use LegacitiForWp\Database\PersonRepository;
use LegacitiForWp\Database\PublicationRepository;
use LegacitiForWp\Database\RelationRepository;

final class Router
{
    public function __construct(
        private readonly PersonRepository $personRepo,
        private readonly PublicationRepository $publicationRepo,
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_filter('template_include', [$this, 'resolveTemplate']);
    }

    public function addRewriteRules(): void
    {
        $settings = get_option('legaciti_settings', []);
        $prefix = trim($settings['url_prefix'] ?? '', '/');

        if ($prefix !== '') {
            add_rewrite_rule(
                '^' . $prefix . '/([a-zA-Z0-9_-]+)/?$',
                'index.php?legaciti_person=$matches[1]',
                'top'
            );
        } else {
            add_rewrite_rule(
                '^([a-zA-Z0-9_-]+)/?$',
                'index.php?legaciti_person=$matches[1]',
                'top'
            );
        }

        add_rewrite_rule(
            '^publication/([a-zA-Z0-9_-]+)/?$',
            'index.php?legaciti_publication=$matches[1]',
            'top'
        );
    }

    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'legaciti_person';
        $vars[] = 'legaciti_publication';

        return $vars;
    }

    public function resolveTemplate(string $template): string
    {
        $personNickname = get_query_var('legaciti_person');
        $publicationSlug = get_query_var('legaciti_publication');

        if ($personNickname !== '') {
            $person = $this->personRepo->findByNickname($personNickname);

            if ($person !== null) {
                set_query_var('legaciti_person_data', $person);
                return $this->locateTemplate('person-profile.php');
            }
        }

        if ($publicationSlug !== '') {
            $publication = $this->publicationRepo->findBySlug($publicationSlug);

            if ($publication !== null) {
                set_query_var('legaciti_publication_data', $publication);
                return $this->locateTemplate('publication-profile.php');
            }
        }

        return $template;
    }

    private function locateTemplate(string $templateName): string
    {
        $themeTemplate = locate_template("legaciti/{$templateName}");

        if ($themeTemplate !== '') {
            return $themeTemplate;
        }

        $pluginTemplate = LEGACITI_PLUGIN_DIR . 'templates/' . $templateName;

        if (file_exists($pluginTemplate)) {
            return $pluginTemplate;
        }

        return get_index_template();
    }
}
