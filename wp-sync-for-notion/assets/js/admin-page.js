const __ = wp.i18n.__;
function notionWpSyncSettingsHandler() {
    const $ = jQuery;
    return {
        config: notionWpSyncGetConfig(),
        databases: [],
        pages: [],
        object_index: {},
        current_object: undefined,
        fields: [],
		synchronized: false,

        originalConfigJson: JSON.stringify(notionWpSyncGetConfig()),
        originalPostTypeSlug: notionWpSyncGetConfig().post_type_slug || '',
        loadingDatabasesAndPages: false,
        loadingTables: false,
		serverValidation: {},
		localValidation: {},
        validation: {},
        nonce: document.getElementById('notion-wp-sync-ajax-nonce').value,
		mappingMetabox: $('#notionwpsync-mapping'),
		mappingOptions: {},

		hideNoticeTemp: {},
        init() {
            const self = this;

            const selects = $('#notionwpsync-global-settings #database_objects_id, #notionwpsync-global-settings #page_objects_id');

            // pre-populate object_index
            selects.find('option').each(function () {
                if ($(this).data('full-object')) {
                    self.object_index[$(this).val()] = $(this).data('full-object');
                }
            });

            self.setCurrentDatabaseOrPage();

            selects.each(function () {
                self.setupSelect2($(this), selects);
            });

			self.synchronized = self.config.synchronized;
			$(document).on('notionwpsync/synchronized', function () {
				self.synchronized = true;
			});
			// Update mapping from React
			$(document).on('notionwpsync/mapping-updated', function (e) {
				self.config = {
					...self.config,
					mapping: e.detail
				};
			});

			this.$nextTick(() => this.updateErrorMessages());

			this.validationNotice = document.getElementById('notionwpsync-validation-notice');

			// Validate form when publish button clicked
			var alpineContainer = document.getElementById('notionwpsync-alpine-container');
			document.getElementById('publish').addEventListener('click', function(e) {
				var event = new CustomEvent('validate', { detail: {
						originalEvent: e,
					}});
				alpineContainer.dispatchEvent(event);
			});
        },
        setupSelect2($select, $selects) {
            const self = this;

            let lastResult = { success: false };
            $select.select2({
                multiple: true,
                minimumInputLength: 3,
                maximumSelectionLength: $select.is('#database_objects_id') ? 1 : 0,
                ajax: {
                    url: window.ajaxurl,
                    dataType: 'json',
                    type: "POST",
					delay: 250,
                    data: function (params) {
                        return Object.assign({}, params, {
                            action: 'notion_wp_sync_get_notion_objects',
                            nonce: self.nonce,
                            apiKey: self.config.api_key,
                            objectType: $select.is('#database_objects_id') ? 'database' : 'page',
                        });
                    },
                    processResults: function (response) {

                        const result = {
                            results: []
                        };
						if (!response.success) {
							// Mark api key as invalid
							self.serverValidation.apiKey = {
								valid: false,
								message: response.data.error,
							};
							self.object_index = [];
							return result
						}

						// Mark api key as valid
						self.serverValidation.apiKey = {
							valid: true,
							message: '',
						};

                        lastResult = response;

						response.data.forEach(function (item) {
							self.object_index[item.id] = item;
						})
						result.results = self.toSelect2Format(response.data);

                        return result;
                    }
                },

            }).on('change.select2', function () {
                if ($(this).val().length > 0) {
                    // Don't mix-up object types.
                    $selects.not($select).each(function () {
                      if ($(this).val().length > 0) {
                          $(this).val(null).trigger('change');
                      }
                    });
                }

                self.config.objects_id = $(this).val();
                self.setCurrentDatabaseOrPage();

            });
        },
        toSelect2Format(data) {
            return data.map(function (item) {
                return {
                    id: item.id,
                    text: item.name,
                };
            });
        },
		showNoticeHandler(noticeKey) {
			const self = this;
			return function () {
				self.config.notices[noticeKey] = true;
			}
		},
		hideNoticeHandler(noticeKey) {
			const self = this;
			return function () {
				self.config.notices[noticeKey] = false;
			}
		},
		tempHideNoticeHandler(noticeKey) {
			const self = this;
			return function () {
				self.hideNoticeTemp[noticeKey] = true;
			}
		},
		updateWordPressOptions() {
			const self = this;

			if (self.config.object_type !== 'page' && self.config.post_type === 'nwpsync-content') {
				self.config.post_type = 'post';
			}

			if (self.config.post_type === 'nwpsync-content') {
				self.mappingMetabox.hide();
			} else {
				self.mappingMetabox.show();
			}

			if (self.config.object_type === 'page' && self.config.post_type === 'nwpsync-content') {
				self.config.mapping = [
					{ notion: "title", wordpress: "post::post_title", options: {} },
					{ notion: "__notionwpsync_blocks", wordpress: "post::post_content", options: {} }
				];
			} else if (self.config.mapping.length === 0 && self.config.object_type === 'page' && (self.config.post_type === 'post' || self.config.post_type === 'page')) {
				self.config.mapping = [
					{ notion: "title", wordpress: "post::post_title", options: {} },
					{ notion: "__notionwpsync_blocks", wordpress: "post::post_content", options: {} }
				];
			}

			self.refreshMetaboxMapping();
		},
		updateErrorMessages: function () {
			this.localValidation = {};
			this.inputElements = [...this.$el.querySelectorAll('[data-rules]')];
			this.inputElements.map((input) => {
				const name = input.dataset.name || input.name;
				let value = input.dataset.value;
				if (value === 'config.mapping') {
					value = this.config.mapping;
				} else if (value) {
					value = input.dataset.value.split('.').reduce((a, b) => a[b], this);
				} else {
					value = input._x_model && input._x_model.get() || '';
				}
				const rules = input.dataset.rules;

				if (!this.localValidation[name]) {
					this.localValidation[name] = {
						errorMessages: [],
						blurred: false,
					}
				}

				this.localValidation[name].errorMessages = wp.hooks.applyFilters('notionwpsync.getErrorMessages', [], value, rules, this);
			});
		},
		getErrorMessages: function(name) {
			return this.localValidation[name] && this.localValidation[name].blurred ? this.localValidation[name].errorMessages : [];
		},
		hasErrors: function(name) {
			return this.getErrorMessages(name).length > 0;
		},
		change: function (event) {
			this.updateErrorMessages();
			if (!this.localValidation[event.target.name]) {
				return false;
			}
			if (event.type === "focusout") {
				this.localValidation[event.target.name].blurred = true;
			}
		},
		submit: function(event) {
			let isValid = true;
			this.updateErrorMessages();
			this.refreshMetaboxMapping();
			this.inputElements.map((input) => {
				const name = input.dataset.name || input.name;
				this.localValidation[name].blurred = true;
			});
			for (let name in this.localValidation) {
				if (this.localValidation[name].errorMessages.length > 0) {
					isValid = false;
				}
			}
			if (!isValid) {
				event.detail.originalEvent.preventDefault();
				this.validationNotice.style.display = 'block';
			}
		},
		getValidationCssClass(key) {
			let cssClass = '';
			if (this.serverValidation[key]) {
				if (this.serverValidation[key].valid === true) {
					cssClass = 'dashicons-before dashicons-yes-alt';
				}
				if (this.serverValidation[key].valid === false) {
					cssClass = 'dashicons-before dashicons-dismiss';
				}
			}
			return cssClass;
		},
        configHasChanged() {
            return JSON.stringify(this.config) !== this.originalConfigJson;
        },
        setCurrentDatabaseOrPage() {
			const self = this;
            if (self.config.objects_id && self.config.objects_id.length > 0 && self.object_index[self.config.objects_id[0]]) {
                self.current_object = self.object_index[self.config.objects_id[0]];
                self.config.object_type = self.current_object.type;
            } else  {
                self.current_object = undefined;
                self.config.object_type = '';
            }

			self.updateWordPressOptions();
        },

		refreshMetaboxMapping() {
			const self = this;
			if (!self.current_object) {
				$('#notionwpsync-metabox-mapping').empty();
				return;
			}
			window.notionWPSyncRenderMetaboxMapping({
				id: 'notionwpsync-metabox-mapping',
				i18n: wp.i18n,
				mappingInit: [ ...self.config.mapping ],
				defaultMappingOptions: window.notionWpSync[self.config.module] ? window.notionWpSync[self.config.module].mappingOptions : {},
				isOptionAvailable(value) {
					return wp.hooks.applyFilters('notionwpsync.isOptionAvailable', true, value, self);
				},
				fields: self.current_object ? self.current_object.fields : [],
				config: {
					post_type: self.config.post_type,
					post_type_slug: self.config.post_type_slug,
				},
				localValidation: self.localValidation && self.localValidation['mapping'] ? self.localValidation['mapping'] : {
					errorMessages: [],
					blurred: false,
				}
			});
		},

		copyToClipboard(button) {
				// Get the text field
			var copyText = button.previousSibling;

			// Select the text field
			copyText.select();
			copyText.setSelectionRange(0, 99999); // For mobile devices

			// Copy the text inside the text field
			navigator.clipboard.writeText(copyText.value);

			jQuery(button).addClass('is-copied');
			setTimeout(function () {
				jQuery(button).removeClass('is-copied');
			}, 2000)
		},

		today() {
			const d = new Date();
			d.setUTCHours(0,0,0,0);
			return d;
		},

		hasPageContentInMapping() {
			return this.config && this.config.mapping && this.config.mapping.reduce((carry, mappingOption) => {
				return carry || mappingOption.notion.indexOf('__notionwpsync_blocks') > -1;
			}, false);
		},

		initColorField() {
			const self = this;
			$('.notionwpsync-admin-color-field').wpColorPicker({
				change: function () {
					// This here is the input.
					const $input = $(this);
					// Defer $input.val(), sometimes it's a little bit off?!
					setTimeout(() => {
						self.config.default_text_color = $input.val();
					});
				},
				clear: function () {
					self.config.default_text_color = '';
				}
			});
		}

    }
}


function notionWpSyncGetConfig() {
    var config = window.notionwpsyncImporterData || {};
    if (!config.hasOwnProperty('mapping')) {
        config.mapping = [];
    }

    if (!config.hasOwnProperty('validation')) {
        config.validation = {};
    }

    if (!config.hasOwnProperty('scheduled_sync')) {
        config.scheduled_sync = {
            type: 'manual',
            recurrence: '',
        };
    }

    for (var i=0;i<config.mapping.length;i++) {
        if (!config.mapping[i].hasOwnProperty('options')) {
            config.mapping[i].options = {};
        }
    }

    if (!config.hasOwnProperty('objects_id')) {
        config.objects_id = [];
    }


	if (!config.hasOwnProperty('page_scope')) {
		config.page_scope = 'no';
	}

	if (!config.hasOwnProperty('notices')) {
		config.notices = {};
	}

	if (!config.hasOwnProperty('module')) {
		config.module = 'post';
	}

	// Pro > Free compatibility.
	if (config.post_type !== 'post' && config.post_type !== 'page') {
		config.post_type = 'post';
		config.filters = [];
	}

    return config;
}

(function($) {
    var $nonceField;
	var $importButton;
	var $cancelButton;
	var $feedback;
	var $infos;
	var originalConfigJson;
	var timeout;

	function init() {
        $nonceField = $('#notion-wp-sync-trigger-update-nonce');
		$importButton = $('#notionwpsync-import-button');
		$cancelButton = $('#notionwpsync-cancel-button');
		$feedback = $('#notionwpsync-import-feedback');
		$infos = $('#notionwpsync-import-stats');

        originalConfigJson = JSON.stringify(notionWpSyncGetConfig());

		$importButton.on('click', function() {
			var importerId = $(this).data('importer');
			triggerUpdate(importerId);
		});

		$cancelButton.on('click', function() {
			var importerId = $importButton.data('importer');
			cancelImport(importerId);
		});

		if ($importButton.hasClass('loading')) {
			$importButton.attr('disabled', 'disabled');
			var importerId = $importButton.data('importer');
			getProgress(importerId);
		}


		$(window).on('beforeunload', beforeUnload);

        $('#delete-action').on('click', function() {
            $(window).off('beforeunload', beforeUnload);

			return wp.hooks.applyFilters('notionwpsync.deleteConnection', true, this);
        })
        $('#post').on('submit', function() {
            $(window).off('beforeunload', beforeUnload);
        })
    }

	function triggerUpdate(importerId) {
		clearTimeout(timeout);
		$importButton.addClass('loading').attr('disabled', 'disabled');
		$feedback.html(window.notionWpSyncI18n.startingUpdate || 'In progress...').show();
		var data = {
			'action': 'notion_wp_sync_trigger_update',
			'nonce': $nonceField.val(),
			'importer': importerId,
		};
		$.post(window.ajaxurl, data, function(response) {
			$feedback.html(response.data.feedback);
			if (response.success) {
				getProgress(importerId);
			}
			else {
				$importButton.removeClass('loading').removeAttr('disabled');
				$infos.html(response.data.infosHtml);
				timeout = setTimeout(function() {
					$feedback.fadeOut();
				}, 6000);
			}
		}).fail(function() {
			$importButton.removeClass('loading').removeAttr('disabled');
		});
	}

	function cancelImport(importerId) {
		clearTimeout(timeout);
		$importButton.removeClass('loading').removeAttr('disabled');
		$cancelButton.addClass('loading').attr('disabled', 'disabled');
		$feedback.html(window.notionWpSyncI18n.canceling || 'Canceling...').show();
		var data = {
			'action': 'notion_wp_sync_cancel_import',
			'nonce': $nonceField.val(),
			'importer': importerId,
		};
		$.post(window.ajaxurl, data, function(response) {
			$feedback.html(response.data.feedback);
			$cancelButton.removeClass('loading').removeAttr('disabled').hide();
			$infos.html(response.data.infosHtml);
			timeout = setTimeout(function() {
				$feedback.fadeOut();
			}, 6000);
		}).fail(function() {
			$cancelButton.removeClass('loading').removeAttr('disabled');
		});
	}

	function getProgress(importerId) {
		if (!$importButton.hasClass('loading')) {
			return;
		}
		$cancelButton.show();
		var data = {
			'action': 'notion_wp_sync_get_progress',
			'nonce': $nonceField.val(),
			'importer': importerId,
		};
		$.post(window.ajaxurl, data, function(response) {
			$feedback.html(response.data.feedback);
			if (response.data.infosHtml || !response.success) {
				$importButton.removeClass('loading').removeAttr('disabled');
				$cancelButton.removeClass('loading').removeAttr('disabled').hide();
				$infos.html(response.data.infosHtml);
				timeout = setTimeout(function() {
					$feedback.fadeOut();
				}, 6000);
			}
			else {
				setTimeout(function() {
					getProgress(importerId);
				}, 3000);
			}

		}).fail(function() {
			$importButton.removeClass('loading').removeAttr('disabled');
		});
	}


	function beforeUnload() {
        if ( originalConfigJson !== $('[name="content"]').val() ) {
            return "You have unsaved changes.";
        }
    }

    $(init);
})(jQuery);

(function($) {
    function init() {
        $(document).tooltip({
            items: '.notionwpsync-tooltip',
            tooltipClass: 'arrow-bottom',
            content: function() {
                return $(this).attr('aria-label');
            },
            position: {
                my: 'center bottom',
                at: 'center-3 top-11',
            },
            open: function (event, ui) {
                self = this;
                if (typeof (event.originalEvent) === 'undefined') {
                    return false;
                }

                var $id = ui.tooltip.attr('id');
                $('div.ui-tooltip').not('#' + $id).remove();
            },
            close: function (event, ui) {
                ui.tooltip.hover(function () {
                    $(this).stop(true).fadeTo(400, 1);
                },
                function () {
                    $(this).fadeOut('500', function() {
                        $(this).remove();
                    });
                });
            }
        });
    }

    $(init);
})(jQuery);



/**
 * Validation: required field rule
 */
wp.hooks.addFilter('notionwpsync.getErrorMessages', 'wpconnect/notionwpsync/errors/required', function(messages, value, rules) {
	if (rules.indexOf('required') > -1 && value.length === 0) {
		messages.push('This field is required');
	}
	return messages;
});

/**
 * Validation: mappingRequired field rule
 */
wp.hooks.addFilter('notionwpsync.getErrorMessages', 'wpconnect/notionwpsync/errors/mappingRequired', function(messages, value, rules) {
	if (rules.indexOf('mappingRequired') > -1 && value.length > 0) {
		const oneFieldIsEmpty = value.reduce(function (result, mapping) {
			if ('' === mapping.wordpress) {
				result = true;
			}
			return result;
		}, false);
		if (oneFieldIsEmpty) {
			messages.push('Please select an option in the "Import As" column for all mappings');
		}
		const oneCustomFieldEmpty = value.reduce(function (result, mapping) {
			if (mapping.wordpress && mapping.wordpress.split('::')[1] === 'custom_field' && mapping.options && (!mapping.options.name || mapping.options.name === '')) {
				result = true;
			}
			return result;
		}, false);
		if (oneCustomFieldEmpty) {
			messages.push('"Custom Field" fields can\'t be empty.');
		}
	}
	return messages;
});
