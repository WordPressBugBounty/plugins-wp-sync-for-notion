<?php
/**
 * Display connection header.
 *
 * @package Notion_Wp_Sync
 */

/**
 * Connection header.
 */
return function () {
	?>
	<div class="notionwpsync-admin-header">
		<h2>
			<a
				class="notionwpsync-admin-header-link"
				href="<?php echo esc_url( admin_url( 'edit.php?post_type=nwpsync-connection' ) ); ?>">

				<img
					class="notionwpsync-admin-header-logo" width="20"
					src="<?php echo esc_attr( plugins_url( 'assets/images/notion-logo-black.svg', __DIR__ ) ); ?>"/>
				<span><?php esc_html_e( 'WP Sync for Notion', 'wp-sync-for-notion' ); ?></span>
			</a>
		</h2>

		<a class="notionwpsync-admin-header-promo" href="https://wpconnect.co/notion-wordpress-integration/#pro-version" target="_blank">
			<?php echo wp_kses( __( '👋 Want more features? <strong>Upgrade to Pro Version</strong>! 🚀', 'wp-sync-for-notion' ), array( 'strong' => array() ) ); ?>
			<svg class="notionwpsync-admin-header-promo-icon" fill="#ffffff" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 26 26" width="12" height="12"><path d="M 12.3125 0 C 10.425781 0.00390625 10.566406 0.507813 11.5625 1.5 L 14.78125 4.71875 L 9.25 10.25 C 8.105469 11.394531 8.105469 13.230469 9.25 14.375 L 11.6875 16.78125 C 12.832031 17.921875 14.667969 17.925781 15.8125 16.78125 L 21.34375 11.28125 L 24.5 14.4375 C 25.601563 15.539063 26 15.574219 26 13.6875 L 26 3.40625 C 26 -0.03125 26.035156 0 22.59375 0 Z M 4.875 5 C 2.183594 5 0 7.183594 0 9.875 L 0 21.125 C 0 23.816406 2.183594 26 4.875 26 L 16.125 26 C 18.816406 26 21 23.816406 21 21.125 L 21 14.75 L 18 17.75 L 18 21.125 C 18 22.160156 17.160156 23 16.125 23 L 4.875 23 C 3.839844 23 3 22.160156 3 21.125 L 3 9.875 C 3 8.839844 3.839844 8 4.875 8 L 8.3125 8 L 11.3125 5 Z"/></svg>
		</a>


		<a class="notionwpsync-admin-header-wpco" href="https://wpconnect.co/" target="_blank">
			<img
				width="20"
				src="<?php echo esc_attr( plugins_url( 'assets/images/logo-wpconnect.svg', __DIR__ ) ); ?>"/>
		</a>
	</div>
	<?php
};
