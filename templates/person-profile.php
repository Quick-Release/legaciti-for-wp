<?php

declare(strict_types=1);

/**
 * Person Profile Template
 *
 * Available variables:
 *   $person — LegacitiForWp\Models\Person
 *
 * Theme override: place legaciti/person-profile.php in your theme.
 */

use LegacitiForWp\Database\PublicationRepository;
use LegacitiForWp\Database\RelationRepository;

if (! defined('ABSPATH')) {
    exit;
}

$person = get_query_var('legaciti_person_data');

if (! $person) {
    return;
}

global $wpdb;
$relationRepo = new RelationRepository();
$publications = $relationRepo->getPublicationsForPerson($person->id);

get_header();
?>

<div class="legaciti-person-profile">
    <?php if ($person->avatarUrl): ?>
        <img src="<?php echo esc_url($person->avatarUrl); ?>"
             alt="<?php echo esc_attr($person->fullName()); ?>"
             class="legaciti-person-avatar" />
    <?php endif; ?>

    <h1><?php echo esc_html($person->fullName()); ?></h1>

    <?php if ($person->title): ?>
        <p class="legaciti-person-title"><?php echo esc_html($person->title); ?></p>
    <?php endif; ?>

    <?php if ($person->email): ?>
        <p class="legaciti-person-email">
            <a href="mailto:<?php echo esc_attr($person->email); ?>">
                <?php echo esc_html($person->email); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($person->bio): ?>
        <div class="legaciti-person-bio">
            <?php echo wp_kses_post($person->bio); ?>
        </div>
    <?php endif; ?>

    <?php if (count($publications) > 0): ?>
        <h2><?php echo esc_html__('Publications', 'legaciti-for-wp'); ?></h2>
        <ul class="legaciti-person-publications">
            <?php foreach ($publications as $publication): ?>
                <li>
                    <a href="<?php echo esc_url(home_url('/publication/' . $publication->slug)); ?>">
                        <?php echo esc_html($publication->title); ?>
                    </a>
                    <?php if ($publication->publicationDate): ?>
                        <span class="legaciti-pub-date"><?php echo esc_html($publication->publicationDate); ?></span>
                    <?php endif; ?>
                    <?php if ($publication->journal): ?>
                        <span class="legaciti-pub-journal"><?php echo esc_html($publication->journal); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php
get_footer();
