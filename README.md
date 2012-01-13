This is here for nostalgia only, Google Buzz doesn't exist anymore.

The "ghetto" refers to PHP.

About
=====

ghetto-life-stream takes the feed provided by Google Buzz and uses
it to create a life stream of entries from various sources, with
specific handling for each different type of source.

Sources are either directly integrated into Google Buzz such as
Twitter and Flickr, or are subscribed to via Google Reader which when
shared are then sent to Google Buzz. So far the following sources are
handled:

- Google Buzz
- Google Reader
- Twitter
- Flickr
- Github Gist
- Slashdot (via Google Reader)
- Reddit (via Google Reader)

Other features included are a stand-alone RSS feed (for your blog)
and a file-based caching mechanism for each external resource,
namely the Google Buzz and blog feeds.

Example usage:

   <? foreach (get_buzz_entries() as $entry) { ?>
   <div>
       <a href="<?= $entry["profile"] ?>"><img
           src="/img/icons/<?= $entry["source"] ?>.png"
           alt="<?= $entry["source"] ?>" /></a>
       <p>
           <?= $entry["body"] ?>
           <? if ($entry["link"]) { ?>
            <a class="more" href="<?= $entry["link"] ?>">read more</a>
           <? } ?>
           - <?= $entry["time"] ?>
       </p>
   </div>
   <? } ?>
