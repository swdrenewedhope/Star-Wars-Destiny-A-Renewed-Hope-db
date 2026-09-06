(function ui_deckimport(ui, $) {

var name_regexp;

ui.on_content_change = function on_content_change(event) {
	var text = $(content).val(),
		slots = {}, 
		faction_code, 
		faction_name;
	
	text.match(name_regexp).forEach(function (token) {
		var qty = 1, name = token.trim(), card;
		if(token[0] === '(') {
			return;
		}
		if(name.match(/^(\d+)x (.*)/)) {
			qty = parseInt(RegExp.$1, 10);
			name = RegExp.$2.trim();
		}
		if(card = app.data.cards.findOne({ name: name })) {
			slots[card.code] = qty;
		}
		else if(faction = app.data.factions.findOne({ name: name })) {
			faction_code = faction.code;
			faction_name = faction.name;
		}
		else {
			console.log('rejecting string ['+name+']');
		}
	})
	
	app.deck.init({
		faction_code: faction_code,
		faction_name: faction_name,
		slots: slots
	});
	app.deck.display('#deck');
	$('input[name=content').val(app.deck.get_json());
	$('input[name=faction_code').val(faction_code);
}

ui.on_dom_loaded = function on_dom_loaded() {
	$('#content').change(ui.on_content_change);
};

ui.on_data_loaded = function on_data_loaded() {
	var characters = _.unique(_.map(app.data.cards.find(), 'name').join('').split('').sort()).join('');
	name_regexp = new RegExp('\\(?[\\d' + characters.replace(/[[\](){}?*+^$\\.|]/g, '\\$&') + ']+\\)?', 'g');
};

ui.on_all_loaded = function on_all_loaded() {
};

})(app.ui, jQuery);
