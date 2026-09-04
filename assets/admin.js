(function ($) {
	'use strict';

	function nextIndex($repeater) {
		var max = -1;
		$repeater.find('.az-repeater-row').each(function () {
			var value = parseInt($(this).attr('data-index'), 10);
			if (!isNaN(value)) {
				max = Math.max(max, value);
			}
		});
		return max + 1;
	}

	function initProductSearch($scope) {
		$(document.body).trigger('wc-enhanced-select-init');
		$scope.find('.wc-product-search').each(function () {
			if (!$(this).hasClass('enhanced')) {
				$(document.body).trigger('wc-enhanced-select-init');
			}
		});
	}

	function toggleChannel($row) {
		var type = $row.find('.az-channel-type').val();
		$row.find('.az-channel-fields').attr('hidden', true);
		$row.find('.az-channel-' + type).removeAttr('hidden');
	}

	function toggleCondition($row) {
		var type = $row.find('.az-match-type').val();
		$row.find('.az-condition').attr('hidden', true).find('select').prop('disabled', true);
		$row.find('.az-condition-' + type).removeAttr('hidden').find('select').prop('disabled', false);
	}

	function ruleFromEditor($row) {
		var type = $row.find('.az-match-type').val();
		return {
			id: $row.find('input[name$="[id]"]').val() || '',
			name: $row.find('input[name$="[name]"]').val() || '',
			enabled: $row.find('input[name$="[enabled]"]').is(':checked') ? 1 : 0,
			match_type: type,
			match_values: $row.find('.az-condition-' + type + ' select').val() || [],
			channel_ids: $row.find('input[name$="[channel_ids][]"]:checked').map(function () {
				return $(this).val();
			}).get()
		};
	}

	function populateRuleEditor($row, rule) {
		var type = rule.match_type || 'all';
		$row.find('input[name$="[id]"]').val(rule.id || '');
		$row.find('input[name$="[name]"]').val(rule.name || '');
		$row.find('input[name$="[enabled]"]').prop('checked', !!rule.enabled);
		$row.find('.az-match-type').val(type);
		$row.find('input[name$="[channel_ids][]"]').prop('checked', false);
		(rule.channel_ids || []).forEach(function (channelId) {
			$row.find('input[name$="[channel_ids][]"]').filter(function () {
				return $(this).val() === String(channelId);
			}).prop('checked', true);
		});

		var $values = $row.find('.az-condition-' + type + ' select');
		if ('products' === type) {
			$values.empty();
			(rule.match_options || []).forEach(function (option) {
				$values.append(new Option(option.text, option.id, true, true));
			});
		} else {
			$values.val((rule.match_values || []).map(String));
		}

		toggleCondition($row);
		initProductSearch($row);
	}

	function channelFromEditor($row) {
		var channel = {};
		$row.find(':input[name]').each(function () {
			var $field = $(this);
			var match = $field.attr('name').match(/^channels\[[^\]]+\]\[([^\]]+)\](\[\])?$/);
			if (!match || $field.is(':disabled') || (($field.is(':checkbox') || $field.is(':radio')) && !$field.is(':checked'))) {
				return;
			}

			if (match[2]) {
				channel[match[1]] = channel[match[1]] || [];
				channel[match[1]].push($field.val());
			} else {
				channel[match[1]] = $field.val();
			}
		});
		return channel;
	}

	$('.az-repeater').each(function () {
		var $table = $(this);
		var $container = $table.closest('.az-rules-editor, .az-channels-editor, form');
		var template = $container.find('.az-row-template').html();

		$container.on('click', '.az-add-row', function () {
			var kind = $table.attr('data-kind');
			var editorSelector = 'rules' === kind ? '.az-rule-edit-row' : '.az-channel-edit-row';
			if ($table.find(editorSelector).length) {
				$table.find(editorSelector + ' input[name$="[name]"]').trigger('focus');
				return;
			}
			var index = nextIndex($table);
			var html = template.replaceAll('__INDEX__', String(index));
			var $row = $(html);
			$table.find('tbody').append($row);
			$row.data('original-html', '');
			toggleChannel($row);
			toggleCondition($row);
			initProductSearch($row);
			$row.find('input[name$="[name]"]').trigger('focus');
		});

		$container.on('click', '.az-remove-row', function () {
			var $button = $(this);
			var $row = $button.closest('.az-repeater-row');

			if ('channels' === $table.attr('data-kind')) {
				var channelId = $row.attr('data-channel-id') || $row.find('input[name$="[id]"]').val() || '';
				var channelName = $row.attr('data-channel-name') || $row.find('input[name$="[name]"]').val() || '';
				var channelConfirmMessage = AZWooAlertsAdmin.deleteChannelConfirm.replace('%s', channelName);
				if (!window.confirm(channelConfirmMessage)) {
					return;
				}
				if (!channelId) {
					$row.remove();
					return;
				}

				var channelOriginalLabel = $button.text();
				$button.prop('disabled', true).text(AZWooAlertsAdmin.deletingChannelLabel);
				$.post(AZWooAlertsAdmin.ajaxUrl, {
					action: 'az_woo_alerts_delete_channel',
					nonce: AZWooAlertsAdmin.deleteChannelNonce,
					channel: channelId
				}).done(function (response) {
					if (response && response.success) {
						$row.remove();
						return;
					}
					window.alert((response && response.data && response.data.message) || AZWooAlertsAdmin.deleteChannelError);
				}).fail(function (xhr) {
					var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
					window.alert(message || AZWooAlertsAdmin.deleteChannelError);
				}).always(function () {
					$button.prop('disabled', false).text(channelOriginalLabel);
				});
				return;
			}

			var ruleId = $row.attr('data-rule-id') || $row.find('input[name$="[id]"]').val() || '';
			var ruleName = $row.attr('data-rule-name') || $row.find('input[name$="[name]"]').val() || '';
			var confirmMessage = AZWooAlertsAdmin.deleteRuleConfirm.replace('%s', ruleName);
			if (!window.confirm(confirmMessage)) {
				return;
			}

			if (!ruleId) {
				$row.remove();
				return;
			}

			var originalLabel = $button.text();
			$button.prop('disabled', true).text(AZWooAlertsAdmin.deletingRuleLabel);
			$.post(AZWooAlertsAdmin.ajaxUrl, {
				action: 'az_woo_alerts_delete_rule',
				nonce: AZWooAlertsAdmin.deleteRuleNonce,
				rule: ruleId
			}).done(function (response) {
				if (response && response.success) {
					$row.remove();
					return;
				}
				window.alert((response.data && response.data.message) || AZWooAlertsAdmin.deleteRuleError);
			}).fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				window.alert(message || AZWooAlertsAdmin.deleteRuleError);
			}).always(function () {
				$button.prop('disabled', false).text(originalLabel);
			});
		});

		$container.on('click', '.az-edit-channel', function () {
			var $button = $(this);
			var $displayRow = $button.closest('.az-channel-display-row');
			var originalLabel = $button.text();
			$button.prop('disabled', true).text(AZWooAlertsAdmin.loadingLabel);

			$.post(AZWooAlertsAdmin.ajaxUrl, {
				action: 'az_woo_alerts_edit_channel',
				nonce: AZWooAlertsAdmin.editChannelNonce,
				channel: $displayRow.attr('data-channel-id'),
				index: nextIndex($table)
			}).done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					var $editor = $(response.data.html);
					$editor.data('original-html', $displayRow.prop('outerHTML'));
					$displayRow.replaceWith($editor);
					toggleChannel($editor);
					$editor.find('input[name$="[name]"]').trigger('focus');
					return;
				}
				window.alert((response && response.data && response.data.message) || AZWooAlertsAdmin.editChannelError);
			}).fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				window.alert(message || AZWooAlertsAdmin.editChannelError);
			}).always(function () {
				$button.prop('disabled', false).text(originalLabel);
			});
		});

		$container.on('click', '.az-cancel-channel', function () {
			var $row = $(this).closest('.az-channel-edit-row');
			var originalHtml = $row.data('original-html');
			if (originalHtml) {
				$row.replaceWith(originalHtml);
			} else {
				$row.remove();
			}
		});

		$container.on('click', '.az-save-channel', function () {
			var $button = $(this);
			var $row = $button.closest('.az-channel-edit-row');
			var originalLabel = $button.text();
			var channel = channelFromEditor($row);

			$button.prop('disabled', true).text(AZWooAlertsAdmin.savingChannelLabel);
			$.post(AZWooAlertsAdmin.ajaxUrl, {
				action: 'az_woo_alerts_save_channel',
				nonce: AZWooAlertsAdmin.saveChannelNonce,
				channel: channel
			}).done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					$row.replaceWith(response.data.html);
					return;
				}
				window.alert((response && response.data && response.data.message) || AZWooAlertsAdmin.saveChannelError);
			}).fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				window.alert(message || AZWooAlertsAdmin.saveChannelError);
			}).always(function () {
				$button.prop('disabled', false).text(originalLabel);
			});
		});

		$container.on('click', '.az-edit-rule', function () {
			var $displayRow = $(this).closest('.az-rule-display-row');
			var rule;
			try {
				rule = JSON.parse($displayRow.attr('data-rule'));
			} catch (error) {
				window.alert(AZWooAlertsAdmin.saveRuleError);
				return;
			}

			var index = nextIndex($table);
			var $editor = $(template.replaceAll('__INDEX__', String(index)));
			$editor.data('original-html', $displayRow.prop('outerHTML'));
			$displayRow.replaceWith($editor);
			populateRuleEditor($editor, rule);
			$editor.find('input[name$="[name]"]').trigger('focus');
		});

		$container.on('click', '.az-cancel-rule', function () {
			var $row = $(this).closest('.az-rule-edit-row');
			var originalHtml = $row.data('original-html');
			if (originalHtml) {
				$row.replaceWith(originalHtml);
			} else {
				$row.remove();
			}
		});

		$container.on('click', '.az-save-rule', function () {
			var $button = $(this);
			var $row = $button.closest('.az-rule-edit-row');
			var originalLabel = $button.text();
			var rule = ruleFromEditor($row);

			$button.prop('disabled', true).text(AZWooAlertsAdmin.savingRuleLabel);
			$.post(AZWooAlertsAdmin.ajaxUrl, {
				action: 'az_woo_alerts_save_rule',
				nonce: AZWooAlertsAdmin.saveRuleNonce,
				rule: rule
			}).done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					$row.replaceWith(response.data.html);
					return;
				}
				window.alert((response.data && response.data.message) || AZWooAlertsAdmin.saveRuleError);
			}).fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				window.alert(message || AZWooAlertsAdmin.saveRuleError);
			}).always(function () {
				$button.prop('disabled', false).text(originalLabel);
			});
		});

		$container.on('change', '.az-channel-type', function () {
			toggleChannel($(this).closest('.az-repeater-row'));
		});

		$container.on('change', '.az-match-type', function () {
			toggleCondition($(this).closest('.az-repeater-row'));
		});
	});

	$(document).on('keydown', '.az-secret-input[data-configured="1"]', function () {
		if ($(this).val() === AZWooAlertsAdmin.storedMask) {
			$(this).val('').attr('type', 'password').attr('data-configured', '0');
		}
	});

	$(document).on('click', '.az-reveal-secret', function () {
		var $button = $(this);
		var $input = $button.siblings('.az-secret-input');

		if ($button.attr('data-loaded') === '1') {
			var show = $input.attr('type') === 'password';
			$input.attr('type', show ? 'text' : 'password');
			$button.text(show ? AZWooAlertsAdmin.hideLabel : AZWooAlertsAdmin.revealLabel);
			return;
		}

		$button.prop('disabled', true);
		$.post(AZWooAlertsAdmin.ajaxUrl, {
			action: 'az_woo_alerts_reveal_secret',
			nonce: AZWooAlertsAdmin.revealNonce,
			channel: $button.attr('data-channel'),
			field: $button.attr('data-field')
		}).done(function (response) {
			if (response && response.success && response.data && response.data.value) {
				$input.val(response.data.value).attr('type', 'text').attr('data-configured', '0');
				$button.attr('data-loaded', '1').text(AZWooAlertsAdmin.hideLabel);
				return;
			}
			window.alert((response.data && response.data.message) || AZWooAlertsAdmin.revealError);
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
			window.alert(message || AZWooAlertsAdmin.revealError);
		}).always(function () {
			$button.prop('disabled', false);
		});
	});

	initProductSearch($(document));
	$('.az-rule-row').each(function () {
		toggleCondition($(this));
	});
})(jQuery);
