<?php

declare(strict_types=1);

/**
 * Publication Profile Template
 *
 * Available variables:
 *   $publication — LegacitiForWp\Models\Publication
 *
 * Theme override: place legaciti/publication-profile.php in your theme.
 */

use LegacitiForWp\Database\RelationRepository;

if (! defined('ABSPATH')) {
    exit;
}

$publication = get_query_var('legaciti_publication_data');

if (! $publication) {
    return;
}

$relationRepo = new RelationRepository();
$people = $relationRepo->getPeopleForPublication($publication->id);

get_header();
?>

<div class="legaciti-publication-profile">
    <h1><?php echo esc_html($publication->title); ?></h1>

    <?php if ($publication->publicationDate): ?>
        <p class="legaciti-pub-date"><?php echo esc_html($publication->publicationDate); ?></p>
    <?php endif; ?>

    <?php if ($publication->journal): ?>
        <p class="legaciti-pub-journal"><?php echo esc_html($publication->journal); ?></p>
    <?php endif; ?>

    <?php if ($publication->doi): ?>
        <p class="legaciti-pub-doi">
            DOI: <a href="https://doi.org/<?php echo esc_attr($publication->doi); ?>" target="_blank" rel="noopener">
                <?php echo esc_html($publication->doi); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($publication->abstract): ?>
        <div class="legaciti-pub-abstract">
            <h2><?php echo esc_html__('Abstract', 'legaciti-for-wp'); ?></h2>
            <?php echo wp_kses_post($publication->abstract); ?>
        </div>
    <?php endif; ?>

    <?php if (count($people) > 0): ?>
        <div class="legaciti-pub-authors">
            <h2><?php echo esc_html__('Authors', 'legaciti-for-wp'); ?></h2>
            <ul>
                <?php foreach ($people as $entry): ?>
                    <li>
                        <a href="<?php echo esc_url(home_url('/' . $entry['person']->nickname)); ?>">
                            <?php echo esc_html($entry['person']->fullName()); ?>
                        </a>
                        <?php if ($entry['role']): ?>
                            <span class="legaciti-author-role">(<?php echo esc_html($entry['role']); ?>)</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php
get_footer();
