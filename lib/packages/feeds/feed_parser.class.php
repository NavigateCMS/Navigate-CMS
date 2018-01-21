<?php
/* reads RSS 2 feeds and converts them to an array of navigate items */
/* uses cache */

class feed_parser
{
    public $data;
    public $url;
    public $articles;
    public $cache;

    public function __construct()
    {
        $this->cache = 1800; // keep retrieved url data for 30 minutes
    }

    public function load($url)
    {
        global $website;

        $this->url = $url;
        $hash = md5($url);

        @mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/cache', 0755, true);
        $feed_cache = NAVIGATE_PRIVATE.'/'.$website->id.'/cache/'.$hash.'.feed';

        if($this->cache > 0)
        {
            if(file_exists($feed_cache))
            {
                if(filemtime($feed_cache) > (time() - $this->cache))
                {
                    $this->data = file_get_contents($feed_cache);
                    return;
                }
            }
        }
        $this->data = file_get_contents($url);
        file_put_contents($feed_cache, $this->data);
    }

    public function set_cache($seconds=null)
    {
        if(!empty($seconds))
            $this->cache = $seconds;
    }

    // author: Stuart Herbert
    // website: http://blog.stuartherbert.com/php/2007/01/07/using-simplexml-to-parse-rss-feeds/
    public function parse($offset=0, $items=null, $order='newest')
    {
        // RSS 2 parser
        // define the namespaces that we are interested in
        $ns = array
        (
            'content' => 'http://purl.org/rss/1.0/modules/content/',
            'wfw' => 'http://wellformedweb.org/CommentAPI/',
            'dc' => 'http://purl.org/dc/elements/1.1/'
        );

        // obtain the articles in the feeds, and construct an array of articles
        $articles = array();

        // step 1: get the feed
        $rawFeed = $this->data;
        if(empty($rawFeed))
            return array();

        $xml = @new SimpleXmlElement($rawFeed);

        // step 2: extract the channel metadata
        $channel = array();
        $channel['title']       = (string)$xml->channel->title;
        $channel['link']        = (string)$xml->channel->link;
        $channel['description'] = (string)$xml->channel->description;
        $channel['pubDate']     = (string)$xml->pubDate;
        $channel['timestamp']   = strtotime((string)$xml->pubDate);
        $channel['generator']   = (string)$xml->generator;
        $channel['language']    = (string)$xml->language;

        $twitter = (strpos($channel['link'], 'twitter.com')!==false);

        // step 3: extract the articles
        foreach ($xml->channel->item as $item)
        {
            $article = array();
            $article['channel'] = $channel['title'];
            $article['title'] = (string)$item->title;
            $article['link'] = (string)$item->link;
            $article['comments'] = (string)$item->comments;
            $article['pubDate'] = (string)$item->pubDate;
            $article['timestamp'] = strtotime((string)$item->pubDate);
            $article['description'] = (string) trim($item->description);
            $article['isPermaLink'] = (string)$item->guid['isPermaLink'];

            // get data held in namespaces
            $content = $item->children($ns['content']);
            $dc      = $item->children($ns['dc']);
            $wfw     = $item->children($ns['wfw']);

            $article['creator'] = (string) $dc->creator;
            foreach ($dc->subject as $subject)
                $article['subject'][] = (string)$subject;

            $article['content'] = (string)trim($content->encoded);
            $article['commentRss'] = (string)$wfw->commentRss;

            if($twitter)
            {
                // remove twitter username, by default twitter returns:
                // UserName: Tweet content
                $article['title'] = substr($article['title'], strpos($article['title'], ': ') + 2);
            }
            // add this article to the list
            $articles[$article['timestamp']] = $article;
        }

// TODO: reorder items

        $articles = array_slice($articles, $offset, $items);

        // at this point, $channel contains all the metadata about the RSS feed,
        // and $articles contains an array of articles for us to repurpose

        $count = count($xml->channel->item);

        return array($channel, $articles, $count);
    }
}

?>