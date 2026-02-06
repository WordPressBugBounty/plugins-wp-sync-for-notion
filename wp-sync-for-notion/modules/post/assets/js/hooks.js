/**
 * Filter available options depending on post type features
 */
wp.hooks.addFilter('notionwpsync.isOptionAvailable', 'wpconnect/notionwpsync/isOptionAvailable', function(available, value, notionWpSyncSettingsHandler) {
    if (notionWpSyncSettingsHandler.config.module === 'post') {
        const featuresByPostType = window.notionWpSync.post.extraConfig.featuresByPostType || {};

        let postType = notionWpSyncSettingsHandler.config.post_type || '';
        if (postType === 'custom') {
            const newPostType = notionWpSyncSettingsHandler.config.post_type_slug || '';
            if (newPostType && featuresByPostType.hasOwnProperty(newPostType)) {
                postType = newPostType;
            }
        }

		const group = value.substring(0, value.indexOf('::'));
		const feature = value.substring(value.indexOf('::') + 2);

        // Check if feature is available for post type
        if (featuresByPostType.hasOwnProperty(postType) && featuresByPostType[postType].hasOwnProperty(group) && Array.isArray(featuresByPostType[postType][group])) {
            available = featuresByPostType[postType][group].indexOf(feature) > -1;
        }
    }
    return available;
});

/**
 * Alert for declared CPT on delete
 */
wp.hooks.addFilter( 'notionwpsync.deleteConnection', 'wpconnect/notionwpsync/deleteConnection', function(returnValue, notionWpSync) {
	const postType = notionWpSyncGetConfig().post_type || '';
    if (postType === 'custom') {
        if (confirm(window.notionWpSyncL10n.deleteActionConfirmation || 'You have a Custom Post Type declared using this connection. Are you sure to delete it?')) {
            returnValue = true;
        } else {
            returnValue = false;
        }
    }
    return returnValue;
});

/**
 * Validation: slug field
 */
wp.hooks.addFilter('notionwpsync.getErrorMessages', 'wpconnect/notionwpsync/errors/slug', function(messages, value, rules) {
    if (rules.indexOf('slug') > -1 && value.length > 0 && !value.match(/^[a-z0-9-_]+$/)) {
        messages.push(window.notionWpSyncL10n.slugErrorMessage || 'Only lowercase alphanumeric characters, dashes, and underscores are allowed.');
    }
    return messages;
});

/**
 * Validation: allowed CPT slug
 */
const originalPostTypeSlug = notionWpSyncGetConfig().post_type_slug || '';
wp.hooks.addFilter('notionwpsync.getErrorMessages', 'wpconnect/notionwpsync/errors/allowedCptSlug', function(messages, value, rules) {
	const reservedSlugs = window.notionWpSync.post.extraConfig.reservedCptSlugs || Array();
    if (rules.indexOf('allowedCptSlug') > -1 && value !== originalPostTypeSlug && reservedSlugs.indexOf(value) > -1) {
        messages.push(window.notionWpSyncL10n.allowedCptSlugErrorMessage || 'This slug is already in use, please choose another.');
    }
    return messages;
});
