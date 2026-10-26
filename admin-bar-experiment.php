<?php
/**
 * Plugin Name:       Admin Bar Experiment
 * Description:       Experimental admin bar tweaks: an inert WordPress logo (that reveals the admin menu as an overlay inside the block editors), a new Help menu, and an "About WordPress" link on the footer version number.
 * Version:           0.1.1
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Jay Koster
 * License:           GPL-2.0-or-later
 * Text Domain:       admin-bar-experiment
 *
 * @package AdminBarExperiment
 */

namespace AdminBarExperiment;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.1';

/**
 * Whether the current request is a block editor screen (post editor or Site Editor).
 *
 * @return bool
 */
function is_editor_screen() {
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}

	return 'site-editor' === $screen->id || $screen->is_block_editor();
}

/**
 * Reworks the WordPress logo node and adds the Help menu.
 *
 * Runs late so the `wp-logo-external` group (added at priority 200) exists.
 *
 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
 */
function customize_admin_bar( $wp_admin_bar ) {
	if ( ! $wp_admin_bar->get_node( 'wp-logo' ) ) {
		return;
	}

	// Strip the WordPress logo submenu entirely.
	foreach ( array( 'about', 'contribute', 'wporg', 'documentation', 'learn', 'support-forums', 'feedback', 'wp-logo-external' ) as $id ) {
		$wp_admin_bar->remove_node( $id );
	}

	$icon = '<span class="ab-icon" aria-hidden="true"></span>';

	if ( is_editor_screen() ) {
		// In the editors the logo becomes a trigger for the admin menu overlay.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'wp-logo',
				'title' => $icon . '<span class="screen-reader-text">' . esc_html__( 'Show navigation', 'admin-bar-experiment' ) . '</span>',
				'href'  => false,
				'meta'  => array(
					'class'    => 'abx-nav-trigger',
					'tabindex' => 0,
				),
			)
		);
	} else {
		// Everywhere else it is purely decorative.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'wp-logo',
				'title' => $icon,
				'href'  => false,
				'meta'  => array(
					'class' => 'abx-inert',
				),
			)
		);
	}

	// Help menu, in the secondary group (to the left of the account menu).
	$wp_admin_bar->add_node(
		array(
			'id'     => 'abx-help',
			'parent' => 'top-secondary',
			'title'  => '<span class="ab-icon" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Help', 'admin-bar-experiment' ) . '</span>',
			'href'   => false,
			'meta'   => array(
				'menu_title' => __( 'Help', 'admin-bar-experiment' ),
				'tabindex'   => 0,
			),
		)
	);

	// Reuse core strings/URLs so translations carry over.
	$items = array(
		'abx-help-documentation' => array( __( 'Documentation' ), __( 'https://wordpress.org/documentation/' ) ),
		'abx-help-learn'         => array( __( 'Learn WordPress' ), __( 'https://learn.wordpress.org/' ) ),
		'abx-help-support'       => array( __( 'Support' ), __( 'https://wordpress.org/support/forums/' ) ),
		'abx-help-feedback'      => array( __( 'Feedback' ), __( 'https://wordpress.org/support/forum/requests-and-feedback' ) ),
	);

	foreach ( $items as $id => list( $title, $href ) ) {
		$wp_admin_bar->add_node(
			array(
				'parent' => 'abx-help',
				'id'     => $id,
				'title'  => $title,
				'href'   => $href,
			)
		);
	}
}
add_action( 'admin_bar_menu', __NAMESPACE__ . '\\customize_admin_bar', 999 );

/**
 * Enqueues assets wherever the admin bar is showing.
 */
function enqueue_assets() {
	if ( ! is_admin_bar_showing() ) {
		return;
	}

	$url = plugin_dir_url( __FILE__ ) . 'assets/';

	wp_enqueue_style( 'admin-bar-experiment', $url . 'admin-bar-experiment.css', array( 'admin-bar', 'dashicons' ), VERSION );
	wp_enqueue_script( 'admin-bar-experiment', $url . 'admin-bar-experiment.js', array(), VERSION, array( 'in_footer' => true ) );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );

/**
 * Flags editor screens so the overlay CSS/JS can scope itself.
 *
 * @param string $classes Space-separated admin body classes.
 * @return string
 */
function admin_body_class( $classes ) {
	return is_editor_screen() ? "$classes abx-editor" : $classes;
}
add_filter( 'admin_body_class', __NAMESPACE__ . '\\admin_body_class' );

/**
 * Links the footer version number to the About WordPress screen.
 *
 * @param string $content Footer text, normally from core_update_footer().
 * @return string
 */
function footer_version_link( $content ) {
	if ( ! current_user_can( 'read' ) ) {
		return $content;
	}

	$version  = get_bloginfo( 'version', 'display' );
	$about    = esc_url( self_admin_url( 'about.php' ) );
	/* translators: %s: WordPress version. */
	$label    = sprintf( __( 'Version %s' ), $version );
	$link     = sprintf(
		'<a href="%1$s" aria-label="%2$s">%3$s</a>',
		$about,
		esc_attr__( 'About WordPress', 'admin-bar-experiment' ),
		esc_html( $label )
	);

	// The common case: plain "Version x.y.z" text.
	if ( false === strpos( $content, '<a' ) ) {
		return '' === trim( wp_strip_all_tags( $content ) ) ? $link : str_replace( $label, $link, $content );
	}

	// An update/development notice already contains a link; add ours alongside.
	return $link . ' &middot; ' . $content;
}
add_filter( 'update_footer', __NAMESPACE__ . '\\footer_version_link', 11 );
