(function ui_card(ui, $) {

	ui.build_legality_table = function build_legality_table() {
		$('div.card-legality-table').each(function () {
			var $table = $(this);
			var code = $table.closest('[data-code]').data('code');
			var allFormats = app.data.formats.find({});

			var FormatMap = [
				{ title: 'Fan', prefixes: ['ARH', 'TCI', 'Galactic'] },
				{ title: 'FFG', prefixes: ['FFG'] }
			];

			var groups = FormatMap.map(function (array) {
				return {
					formats: array.title,
					items: allFormats.filter(function (f) {
						if (array.prefixes.some(function (p) { return f.name.indexOf(p) === 0; })) {
							if (array.title != 'Fan') {
								f.displayName = f.name.replace(array.prefixes[0], '').trim();
							} else {
								f.displayName = f.name;
							}
							return true;
						}
					})
				};
			}).filter(function (group) { return group.items.length > 0; });

			var tpl = Handlebars.templates['ui_card-legality'];
			$table.html(tpl({
				card: app.data.cards.findById(code),
				groups: groups
			}));
			$table.find('[data-toggle="tooltip"]').tooltip();
		});
	}

	ui.build_balance_table = function build_balance_table() {
		$('div.card-balance-table').each(function () {
			var $table = $(this);
			var code = $table.closest('[data-code]').data('code');
			var card = app.data.cards.findById(code);

			var FormatMap = [
				{ title: 'Fan', prefixes: ['ARH', 'TCI', 'Galactic'] },
				{ title: 'FFG', prefixes: ['FFG'] }
			];

			var balanceCard = card.reprint_of
				? app.data.cards.findById(card.reprint_of)
				: card;

			var groups = FormatMap.map(function (array) {
				return {
					formats: array.title,
					items: app.data.formats.find({}).filter(function (f) {
						if (array.prefixes.some(function (p) { return f.name.indexOf(p) === 0; })) {
							if (array.title != 'Fan') {
								f.displayName = f.name.replace(array.prefixes[0], '').trim();
							} else {
								f.displayName = f.name;
							}
							return _.has(f.data.balance, balanceCard.code);
						}
						return false;
					})
				};
			}).filter(function (group) { return group.items.length > 0; });

			if (groups.length > 0) {
				var tpl = Handlebars.templates['ui_card-balance'];
				$table.html(tpl({
					card: card,
					groups: groups
				}));
			} else {
				$table.closest('.card-balance').hide();
			}
		});
	}

	ui.notify = function notify(form, type, message) {
		var alert = $('<div class="alert" role="alert"></div>').addClass('alert-' + type).text(message);
		$(form).after(alert);
	}

	ui.on_all_loaded = function on_all_loaded() {
		ui.build_legality_table();
		ui.build_balance_table();
	};

})(app.ui, jQuery);
