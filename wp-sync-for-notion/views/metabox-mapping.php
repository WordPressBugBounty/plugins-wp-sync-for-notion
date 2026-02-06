<?php
/**
 * Display field mapping.
 *
 * @package Notion_Wp_Sync
 */

/**
 * Mapping view.
 */
return function ( $mapping_validation_rules ) {
	?><div 	id="notionwpsync-metabox-mapping"
				data-value="config.mapping"
				data-name="mapping"
				:class="{'notionwpsync-field--invalid': hasErrors('mapping')}"
				data-rules='<?php echo esc_attr( wp_json_encode( $mapping_validation_rules ) ); ?>'
	></div>
	<?php
	do_action( 'notionwpsync/metabox/after_mapping', 'manage_options' );
};
