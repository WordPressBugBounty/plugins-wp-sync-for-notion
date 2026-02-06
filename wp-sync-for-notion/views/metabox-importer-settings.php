<?php
/**
 * Display modules settings.
 *
 * @package Notion_Wp_Sync
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Display modules settings.
 *
 * @param Notion_WP_Sync_Abstract_Module[] $modules A list of modules.
 * @param \WP_Post $post The post connection.
 */
return function ( $modules, $post ) {
	?>

<table class="form-table">
	<tr valign="top">
		<th scope="row">
			<label for="post_type"><?php esc_html_e( 'Import As', 'wp-sync-for-notion' ); ?></label>
		</th>
		<td>
			<select class="regular-text ltr" name="notionwpsync::module" x-model="config.module" x-init="config.module = config.module || $el.value;" @change="updateWordPressOptions();">
				<?php foreach ( $modules as $module ) : ?>
					<option value="<?php echo esc_attr( $module->get_slug() ); ?>"><?php echo esc_html( $module->get_name() ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
</table>
<hr>
	<?php foreach ( $modules as $module ) : ?>
	<template x-if="config.module === '<?php echo esc_attr( $module->get_slug() ); ?>'">
		<?php $module->render_settings( $post ); ?>
	</template>
		<?php
endforeach;
	?>
	<div x-show="hasPageContentInMapping()">
	<hr>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">
				<label for="default_text_color"><?php esc_html_e( 'Default text color in Notion', 'wp-sync-for-notion' ); ?></label>
				<span class="notionwpsync-tooltip" aria-label="<?php esc_attr_e( 'If needed, choose a default text color. Notion’s default text color is black in Light mode and white in Dark mode.', 'wp-sync-for-notion' ); ?>">?</span>
			</th>
			<td>
				<input type="text" :value="config.default_text_color" x-init="initColorField()" class="notionwpsync-admin-color-field" />
			</td>
		</tr>
	</table>
</div>
	<?php
};
