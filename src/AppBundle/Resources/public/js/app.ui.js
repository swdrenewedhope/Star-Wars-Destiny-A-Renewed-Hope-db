(function ui_deck(ui, $) {

var dom_loaded = new $.Deferred(), data_loaded = new $.Deferred();

ui.on_dom_loaded = function on_dom_loaded() {};
ui.on_data_loaded = function on_data_loaded() {};
ui.on_all_loaded = function on_all_loaded() {};

ui.insert_alert_message = function ui_insert_alert_message(type, message) {
	var alert = $('<div class="alert" role="alert"></div>').addClass('alert-'+type).append(message);
	$('#wrapper>div.container').first().prepend(alert);
}

moment.locale('en', {
    relativeTime: {
        past: '%s ago', m: '1m', mm: '%dm', h: '1h',
        hh: '%dh', d: '1d', dd: '%dd', M: '1mo', MM: '%dmo',
        y: '1y', yy: '%dy'
    }
});

$(document).ready(function () {
	$('[data-toggle="tooltip"]').tooltip();
	$('time').each(function (index, element) {
		var datetime = moment($(element).attr('datetime'));
		$(element).html(datetime.fromNow());
		$(element).attr('title', datetime.format('LLLL'));
	});
	if(typeof ui.on_dom_loaded === 'function') ui.on_dom_loaded();
	dom_loaded.resolve();
});
$(document).on('data.app', function () {
	if(typeof ui.on_data_loaded === 'function') ui.on_data_loaded();
	data_loaded.resolve();
});
$(document).on('start.app', function () {
	if(typeof ui.on_all_loaded === 'function') ui.on_all_loaded();
	$('abbr').each(function (index, element) {
		var keyword = $(this).data('keyword');
		var title = Translator.trans('keyword.'+keyword+'.title');
		if(title) $(element).attr('title', title).tooltip();
	})
});
$.when(dom_loaded, data_loaded).done(function () {
	setTimeout(function () {
		$(document).trigger('start.app');
	}, 0);
});

})(app.ui = {}, jQuery);
