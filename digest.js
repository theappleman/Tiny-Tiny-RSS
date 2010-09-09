var last_feeds = [];

var _active_feed_id = false;
var _active_feed_offset = false;

function viewfeed(feed_id, offset) {
	try {

		if (!feed_id) feed_id = _active_feed_id;

		if (!offset) 
			offset = 0;
		else
			offset = _active_feed_offset + offset;

		var query = "backend.php?op=rpc&subop=digest-update&feed_id=" + feed_id +
				"&offset=" + offset;

		console.log(query);

		new Ajax.Request("backend.php",	{
			parameters: query, 
			onComplete: function(transport) {
				digest_update(transport);
				_active_feed_id = feed_id;
				_active_feed_offset = offset;
				} });

	} catch (e) {
		exception_error("view", e);
	}
}

function find_feed(feeds, feed_id) {
	try {
		for (var i = 0; i < feeds.length; i++) {
			if (feeds[i].id == feed_id)
				return feeds[i];
		}

		return false;

	} catch (e) {
		exception_error("find_feed", e);
	}
}

function get_feed_icon(feed) {
	try {
		if (feed.has_icon)
			return 'icons/' + feed.id + '.ico';

		if (feed.id == -1)
			return 'images/mark_set.png';

		if (feed.id == -2)
			return 'images/pub_set.png';

		if (feed.id == -3)
			return 'images/fresh.png';

		if (feed.id == -4) 
			return 'images/tag.png';

		if (feed.id < -10) 
			return 'images/label.png';

	} catch (e) {
		exception_error("get_feed_icon", e);
	}
}

function add_feed_entry(feed) {
	try {
		var icon_part = "";

		icon_part = "<img src='" + get_feed_icon(feed) + "'/>";

		var tmp_html = "<li>" + 
			icon_part +
			"<a href=\"#\" onclick=\"viewfeed("+feed.id+")\">" + feed.title +
			"<div class='unread-ctr'>" + feed.unread + "</div>" +	
			"</li>";

		$("feeds-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_feed_entry", e);
	}
}

function add_latest_entry(article, feed) {
	try {
		

		//$("latest-content").innerHTML += "bbb";

	} catch (e) {
		exception_error("add_latest_entry", e);
	}
}

function add_headline_entry(article, feed) {
	try {

		var icon_part = "";

		if (article.has_icon) 
			icon_part = "<img src='icons/" + article.feed_id + ".ico'/>";

		var tmp_html = "<li>" + 
			icon_part +
			"<a class='title'>" + article.title + "</a>" +
			"<div class='body'><div class='excerpt'>" + article.excerpt + "</div>" +
			"<div class='info'><a>" + feed.title + "</a> " + " @ " + 
				new Date(article.updated * 1000) + "</div>" +
			"</div></li>";

		$("headlines-content").innerHTML += tmp_html;

	} catch (e) {
		exception_error("add_headline_entry", e);
	}
}

function digest_update(transport) {
	try {
		var feeds = transport.responseXML.getElementsByTagName('feeds')[0];
		var headlines = transport.responseXML.getElementsByTagName('headlines')[0];

		if (feeds) {
			feeds = eval("(" + feeds.firstChild.nodeValue + ")");

			last_feeds = feeds;

			$('feeds-content').innerHTML = "";

			for (var i = 0; i < feeds.length; i++) {
				add_feed_entry(feeds[i]);
			}
		} else {
			feeds = last_feeds;
		}

		if (headlines) {
			headlines = eval("(" + headlines.firstChild.nodeValue + ")");

			$('headlines-content').innerHTML = "";

			Element.hide('headlines-content');

			for (var i = 0; i < headlines.length; i++) {
				add_headline_entry(headlines[i], find_feed(feeds, headlines[i].feed_id));
			}

			$('headlines-content').innerHTML += "<li>" +
				"<div class='body'><a href=\"#\" onclick=\"viewfeed(false, 10)\">" +
			  	__("More articles...") + "</a></div></li>";

			new Effect.Appear('headlines-content');

		}

	} catch (e) {
		exception_error("digest_update", e);
	}
	}

function digest_init() {
	try {
		
		new Ajax.Request("backend.php",	{
			parameters: "backend.php?op=rpc&subop=digest-init",
			onComplete: function(transport) {
				digest_update(transport);
				window.setTimeout('viewfeed(-4)', 100);
				} });

	} catch (e) {
		exception_error("digest_init", e);
	}
}