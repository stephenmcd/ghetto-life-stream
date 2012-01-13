<?

/**
 *
 * ghetto-life-stream takes the feed provided by Google Buzz and uses
 * it to create a life stream of entries from various sources, with
 * specific handling for each different type of source.
 *
 * Sources are either directly integrated into Google Buzz such as
 * Twitter and Flickr, or are subscribed to via Google Reader which when
 * shared are then sent to Google Buzz. So far the following sources are
 * handled:
 *
 *    - Google Buzz
 *    - Google Reader
 *    - Twitter
 *    - Flickr
 *    - Github Gist
 *    - Slashdot (via Google Reader)
 *    - Reddit (via Google Reader)
 *
 * Other features included are a stand-alone RSS feed (for your blog)
 * and a file-based caching mechanism for each external resource,
 * namely the Google Buzz and blog feeds.
 *
 * Example usage:
 *
 *    <? foreach (get_buzz_entries() as $entry) { ?>
 *    <div>
 *        <a href="<?= $entry["profile"] ?>"><img
 *            src="/img/icons/<?= $entry["source"] ?>.png"
 *            alt="<?= $entry["source"] ?>" /></a>
 *        <p>
 *            <?= $entry["body"] ?>
 *            <? if ($entry["link"]) { ?>
 *             <a class="more" href="<?= $entry["link"] ?>">read more</a>
 *            <? } ?>
 *            - <?= $entry["time"] ?>
 *        </p>
 *    </div>
 *    <? } ?>
 *
 */

$SETTINGS = array(
    // Number of seconds to cache feeds for.
    "cache_timeout" => 60 * 5,
    // Relative path to store cached feeds.
    "cache_path" => "db/feeds/",
    // Blog RSS URL.
    "blog_feed" => "http://steve-mc.tumblr.com/rss",
    // Constant string in buzz entries shared via Google Reader from Reddit.
    "reddit_title" => "- reddit.com: what's new online! -",
    // Constant string in buzz entries shared via Google Reader from Slashdot.
    "slashdot_title" => " - Slashdot - ",
    // First and last name found in buzz entries.
    "buzz_fullname" => "Stephen McDonald",
    // Usernames for each integrated buzz entry source.
    "buzz_username" => "stephen.mc",
    "twitter_username" => "stephen_mcd",
    "git_username" => "stephen-mcd",
    "reddit_username" => "stevemcd",
    "flickr_username" => "stephen_mcd",
);

function get_cached_feed($url) {
    /**
     * Takes a feed URL and caches the results in a file.
     */
    global $SETTINGS;
    $xml = "";
    $cache_file = $SETTINGS["cache_path"] .
        preg_replace("/[^a-zA-Z0-9\s]/", "", $url);
    if (file_exists($cache_file) && $_GET["flush"] != "1") {
        $cache_age = time() - filemtime($cache_file);
        if ($cache_age < $SETTINGS["cache_timeout"]) {
            $xml = file_get_contents($cache_file);
            echo "<!-- $url cached from $cache_age seconds ago -->";
        }
    }
    if (!$xml) {
        $xml = @file_get_contents($url);
        if ($xml) {
            echo "<!-- $url retrieved -->";
            $xml = str_replace("media:", "media", $xml);
            $f = fopen($cache_file, "w");
            fwrite($f, $xml);
            fclose($f);
        } else if (file_exists($cache_file)) {
            $xml = file_get_contents($cache_file);
        }
    }
    return simplexml_load_string($xml);
}

function time_since($time) {
    /**
     * Takes a time string and returns the largest unit of time difference since
     * eg: 3 hours ago or 4 weeks ago
     */
    $periods = array(
        array(60 * 60 * 24 * 365, "year"),
        array(60 * 60 * 24 * 30, "month"),
        array(60 * 60 * 24 * 7, "week"),
        array(60 * 60 * 24, "day"),
        array(60 * 60, "hour"),
        array(60, "minute"),
    );
    foreach ($periods as $period) {
        $since = floor((time() - strtotime($time)) / $period[0]);
        if ($since != 0) {
            return "$since $period[1]" . ($since == 1 ? "" : "s") . " ago";
        }
    }
    return "now";
}

function partitioned($start, $end, $text) {
    /**
     * Return the first portion of $text surrounded by $start and $end
     */
    return array_shift(explode($end,
        substr($text, strpos($text, $start) + strlen($start))));
}

function format_twitter($text) {
    /**
     * Takes the text for a tweet and adds HTML anchors where appropriate.
     */
    global $SETTINGS;
    // URLs
    $text = " ".preg_replace( "/(([[:alnum:]]+:\/\/)|www\.)([^[:space:]]*)" .
        "([[:alnum:]#?\/&=])/i", "<a href=\"\\1\\3\\4\" target=\"_blank\">" .
        "\\1\\3\\4</a>", $text);
    // Twitter usernames
    $text = preg_replace("/ +@([a-z0-9_]*) ?/i", " <a href=\"http://" .
        "twitter.com/\\1\" target=\"_blank\">@\\1</a> ", $text);
    // Twitter hashtags
    $text = preg_replace("/ +#([a-z0-9_]*) ?/i", " <a href=\"http://" .
        "twitter.com/search?q=%23\\1\" target=\"_blank\">#\\1</a> ", $text);
    $username_text = $SETTINGS["twitter_username"] . ": ";
    if (strpos($text, $username_text) === 1) {
        $text = substr_replace($text, "", 0, strlen($username_text) + 1);
    }
    return $text;
}

function format_git($text) {
    /**
     * Add link to gist if gist update.
     */
    global $SETTINGS;
    foreach (array("updated", "created") as $gist_type) {
        $gist_text = $SETTINGS["git_username"] . " $gist_type gist: ";
        if (strpos($text, $gist_text) === 0) {
            $gist_id = partitioned($gist_text, " ", $text);
            $gist_text .= $gist_id;
            return str_replace($gist_text, "<a href=\"http://gist.github.com/" .
                $gist_id . "\">$gist_text</a>", $text);
        }
    }
    return $text;
}

function format_reddit($text) {
    /**
     * Pull title from summary.
     */
    global $SETTINGS;
    return substr($text, 0, strpos($text, $SETTINGS["reddit_title"]));
}

function format_flickr($items) {
    /**
     * Extract the images.
     */
    $text = "";
    $count = 0;
    foreach ($items as $item) {
        $img = str_replace("_b.jpg", "_s_d.jpg", $item->mediaplayer["url"]);
        if (strpos($img, "_s_d.jpg") !== false) {
            $count += 1;
            $url = $item["url"];
            $text .= '<a href="' . $url . '"><img src="' . $img . '" /></a>';
        }
    }
    if ($count > 2) {
        $text .= '<br clear="both" />';
    }
    return $text;
}

function format_slashdot($text) {
    /**
     * Remove "username writes" and "Slashdot" title.
     */
    global $SETTINGS;
    $writes_text = " writes \"";
    $writes_pos = strpos($text, $writes_text);
    if ($writes_pos !== false) {
        $text = substr($text, 0, strpos($text, $SETTINGS["slashdot_title"])) .
            ": " . substr($text, $writes_pos + strlen($writes_text) - 1);
    } else {
        $text = str_replace($SETTINGS["slashdot_title"], ": ", $text);
    }
    return str_replace("Read more of this story at Slashdot.", "", $text);
}

function format_body($text) {
    /**
     * Apply some minor formatting to try and achieve xhtml compliance.
     */
     return str_replace(" &", " &amp;",
        str_replace(" target=\"_blank\"", "", $text));
}

function get_buzz_source($entry) {
    /**
     * Returns the type of Buzz entry, eg: ``twitter`` or ``flickr``.
     */
    global $SETTINGS;
    $buzz_text = "Buzz by " . $SETTINGS["buzz_fullname"] . " from ";
    $title = str_replace($buzz_text, "", $entry->title);
    switch ($title) {
        case "Twitter":
        case "Flickr":
            return strtolower($title);
        case "Google Reader":
            if (strpos($entry->summary, $SETTINGS["slashdot_title"]) !== false) {
                return "slashdot";
            } else if (strpos($entry->summary,
                $SETTINGS["reddit_title"]) !== false) {
                return "reddit";
            }
            return "google-reader";
        case $SETTINGS["git_username"] . "&amp;#39;s Activity":
        case $SETTINGS["git_username"] . "&#39;s Activity":
            return "git";
        default:
            return "google-buzz";
    }
}

function get_buzz_entries() {
    /**
     * Returns the array of Buzz entries, determining the type of entry based on
     * the title and setting the various properties such as icon and profile URL.
     */
    global $SETTINGS;
    $entries = array();
    $xml = get_cached_feed("http://buzz.googleapis.com/feeds/" .
        $SETTINGS["buzz_username"] . "/public/posted");
    foreach ($xml->entry as $entry) {

        $source = get_buzz_source($entry);

        switch ($source) {
            case "twitter":
                $profile = "http://twitter.com/" .
                    $SETTINGS["twitter_username"];
                $body = format_twitter($entry->summary);
                $link = "";
                break;
            case "flickr":
                $profile = "http://flickr.com/photos/" .
                    $SETTINGS["flickr_username"] . "/";
                $body = format_flickr($entry->mediacontent);
                $link = "";
                break;
            case "slashdot":
                $profile = "http://slashdot.org";
                $body = format_slashdot($entry->summary);
                $link = partitioned("href=\"", "\"", $entry->content);
                break;
            case "reddit":
                $profile = "http://www.reddit.com/user/" .
                    $SETTINGS["reddit_username"];
                $body = format_reddit($entry->summary);
                $link = partitioned("href=\"", "\"", $entry->content);
                break;
            case "git":
                $profile = "http://github.com/" . $SETTINGS["git_username"];
                $body = format_git($entry->summary);
                $link = "";
                break;
            case "google-reader":
                $profile = "http://www.google.com/profiles/" .
                    $SETTINGS["buzz_username"];
                $body = $entry->summary;
                $link = $entry->link[0]->attributes()->href;
            case "google-buzz":
                $profile = "http://www.google.com/profiles/" .
                    $SETTINGS["buzz_username"];
                $body = $entry->summary;
                $link = "";
        }

        $entries[] = array("profile" => $profile, "source" => $source,
            "body" => format_body($body), "link" => $link,
            "time" => time_since($entry->updated));

    }
    return $entries;
}

function get_blog_entries() {
    /**
     * Returns the array of blog entries using the first paragraph of text.
     */
    global $SETTINGS;
    $entries = array();
    $xml = get_cached_feed($SETTINGS["blog_feed"]);
    foreach ($xml->channel[0]->item as $item) {
        $entries[] = array("link" => $item->link, "title" => $item->title,
            "body" => partitioned("<p>", "</p>", $item->description), "time" =>
            time_since($item->pubDate));
    }
    return $entries;
}

?>
