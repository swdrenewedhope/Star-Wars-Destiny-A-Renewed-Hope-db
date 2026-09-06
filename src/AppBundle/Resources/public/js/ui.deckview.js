(function ui_deck(ui, $) {

var DisplaySort = 'type'

function confirm_delete() {
	$('#delete-deck-name').text(app.deck.get_name());
	$('#delete-deck-id').val(app.deck.get_id());
	$('#deleteModal').modal('show');
}

ui.on_collection_loaded = function on_collection_loaded() {
	ui.sum_reprints_owned();
}

ui.sum_reprints_owned = function sum_reprints_owned() {
	app.data.cards.find({reprint_of: {$exists: true}}).forEach(function(card) {
		var cardReprinted = app.data.cards.findById(card.reprint_of);
		app.data.cards.updateById(card.reprint_of, {
			owned: {
				cards: cardReprinted.owned.cards + card.owned.cards,
				dice: cardReprinted.owned.dice + card.owned.dice
			}
		});
	});
}

ui.do_action_deck = function do_action_deck(event) {

	var action_id = $(this).attr('id');
	if(!action_id) return;

	switch(action_id) {
		case 'btn-delete': confirm_delete(); break;
		case 'btn-sort-type': DisplaySort = 'type'; ui.refresh_deck()(); break;
		case 'btn-sort-position': DisplaySort = 'position'; ui.refresh_deck()(); break;
		case 'btn-sort-faction': DisplaySort = 'faction'; ui.refresh_deck()(); break;
		case 'btn-sort-name': DisplaySort = 'name'; ui.refresh_deck()(); break;
		case 'btn-display-plain': export_plaintext(); break;
		case 'btn-display-bbcode': export_bbcode(); break;
		case 'btn-display-markdown': export_markdown(); break;
	}

}

ui.setup_event_handlers = function setup_event_handlers() {

	$('#btn-group-deck').on({
		click: ui.do_action_deck
	}, 'button[id],a[id]');

}

ui.refresh_deck = function refresh_deck() {
	app.deck.display('#deck');
	app.draw_simulator && app.draw_simulator.reset();
	app.deck_charts && app.deck_charts.setup();
}

ui.on_dom_loaded = function on_dom_loaded() {
	ui.setup_event_handlers();
	app.draw_simulator && app.draw_simulator.on_dom_loaded();
};

ui.on_data_loaded = function on_data_loaded() {
	if(app.collection.isLoaded) {
		ui.on_collection_loaded();
	} else {
		$(document).on('collection.app', function(e) {
			ui.on_collection_loaded();
		});
	}
};

ui.on_all_loaded = function on_all_loaded() {
	app.markdown && app.markdown.update(app.deck.get_description_md() || Translator.trans('decks.defaultemptydesc'), '#description');
	ui.refresh_deck();
};

})(app.ui, jQuery);
