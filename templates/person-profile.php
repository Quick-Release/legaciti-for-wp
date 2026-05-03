<?php

declare(strict_types=1);

/**
 * Person profile (front end)
 *
 * Uses the active theme’s header and footer; main content is the person’s name and photo.
 *
 * Query var: `legaciti_person_data` — LegacitiForWp\Models\Person
 *
 * Theme override: `your-theme/legaciti/person-profile.php`
 */

use LegacitiForWp\Models\Person;

if (! defined('ABSPATH')) {
    exit;
}

/** @var Person|null $person */
$person = get_query_var('legaciti_person_data');

if (! $person instanceof Person) {
    return;
}

$initials = '';
if ($person->firstName !== '') {
    $initials .= mb_strtoupper(mb_substr($person->firstName, 0, 1));
}
if ($person->lastName !== '') {
    $initials .= mb_strtoupper(mb_substr($person->lastName, 0, 1));
}
if ($initials === '') {
    $initials = '?';
}

get_header();
?>

<style>
	.legaciti-person-profile { max-width: 40rem; margin-left: auto; margin-right: auto; padding: 1.5rem 1rem; }
	.legaciti-person-profile__inner { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1rem; }
	.legaciti-person-profile__media { margin: 0; }
	.legaciti-person-profile__image { max-width: min(20rem, 100%); height: auto; display: block; border-radius: 4px; }
	.legaciti-person-profile__placeholder {
		width: 12rem; height: 12rem; max-width: 100%; border-radius: 4px; background: #dcdcde; color: #1d2327;
		display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 600;
	}
	.legaciti-person-profile__name { margin: 0; font-size: clamp(1.5rem, 4vw, 2rem); }
</style>

<main id="primary" class="site-main legaciti-person-profile">
    <article class="legaciti-person-profile__inner">
        <?php if ($person->avatarUrl) : ?>
            <figure class="legaciti-person-profile__media">
                <img
                    src="<?php echo esc_url($person->avatarUrl); ?>"
                    alt=""
                    class="legaciti-person-profile__image"
                    width="320"
                    height="320"
                    loading="lazy"
                    decoding="async"
                />
                <figcaption class="legaciti-person-profile__caption screen-reader-text">
                    <?php echo esc_html($person->fullName()); ?>
                </figcaption>
            </figure>
        <?php else : ?>
            <div class="legaciti-person-profile__placeholder" aria-hidden="true">
                <?php echo esc_html($initials); ?>
            </div>
        <?php endif; ?>

        <h1 class="legaciti-person-profile__name"><?php echo esc_html($person->fullName()); ?></h1>
    </article>
</main>

<?php
get_footer();
