<?php
/*
  A sitemap generator that crawls a given URL and searches for href tags and gets all links on
  that domain. Run it on a local host or push it to a server.
  This crawler does not support user authentication.
  The sitemap XML will only generate the links, the user will need to manully add in the priority
  and the last modified. The map will be saved as "sitemap.xml" in this root folder.

  Help from: http://stackoverflow.com/questions/2313107/how-do-i-make-a-simple-crawler-in-php

  TODO:
  1)  Add header checker for the last modified and default it to that instead of the user adding
      it manually.
  2)  Make more object oriented with regards to separating the inline HTML.
*/
class crawler
{
    protected $_url;
    protected $_depth;
    protected $_host;
    protected $_crawlTime;
    protected $_seen = array();
    protected $_filter = array();
    protected $_xml;

    public function __construct($url, $depth = 5)
    {
        $this->_url = $url;
        $this->_crawlTime = 0.00;
        $this->_depth = $depth;
        $parse = parse_url($url);
        $this->_host = $parse['host'];
        $this->_xml = new SimpleXMLElement('<urlset/>');
    }

    // process anchors
    protected function _processAnchors($content, $url, $depth)
    {
        $dom = new DOMDocument('1.0');
        @$dom->loadHTML($content);
        $anchors = $dom->getElementsByTagName('a');

        // loop through each anchor
        foreach ($anchors as $element)
        {
            $href = $element->getAttribute('href');
            if (0 !== strpos($href, 'http'))
            {
                $path = '/' . ltrim($href, '/');
                if (extension_loaded('http'))
                {
                    $href = http_build_url($url, array('path' => $path));
                }
                else
                {
                    $parts = parse_url($url);
                    $href = $parts['scheme'].'://';
                    $href .= $parts['host'];
                    if (isset($parts['port']))
                    {
                        $href .= ':' . $parts['port'];
                    }
                    $href .= $path;
                }
            }

            // crawl only link that belongs to the start domain
            $this->crawl_page($href, $depth - 1);
        }
    }

    // function to setup a curl request go get the content
    protected function _getContent($url)
    {
        $handle = curl_init($url);

        // CURLOPT_RETURNTRANSFER flag for getting the content
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($handle);

        // you can echo the response of the curl here
        // echo $response;

        $time = curl_getinfo($handle, CURLINFO_TOTAL_TIME);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return array($response, $httpCode, $time);
    }

    // method to display the result of the found URL
    protected function _printResult($url, $depth, $httpcode, $time)
    {
        ob_end_flush();
        $currentDepth = $this->_depth - $depth;
        $count = count($this->_seen);

        // increment overall crawl time
        $this->_crawlTime = $this->_crawlTime + $time;

        echo "STATUS:[$httpcode]\tTIME:[<b>$time</b>]\tDEPTH:[$currentDepth]\t<a href='$url' target='_blank'>CHECK</a>\t:::<b>$url</b> <br>";

        // add the URL to the sitemap XML
        $this->add_to_sitemap($url);

        ob_start();
        flush();
    }

    // function to add a URL to a node in the sitemap
    public function add_to_sitemap($url)
    {
        $parent = $this->_xml->addChild('url');
        $parent->addChild('loc', $url);
        $parent->addChild('lastmod', date("Y-m-d"));
        $parent->addChild('changefreq', "weekly");
        $parent->addChild('priority', "0.5");
    }

    protected function isValid($url, $depth)
    {
        if (strpos($url, $this->_host) === false || $depth === 0 || isset($this->_seen[$url]))
        {
            return false;
        }

        foreach ($this->_filter as $excludePath)
        {
            if (strpos($url, $excludePath) !== false)
            {
                return false;
            }
        }
        return true;
    }

    public function crawl_page($url, $depth)
    {
        if (!$this->isValid($url, $depth))
        {
            return;
        }

        // add to the seen URL
        $this->_seen[$url] = true;

        // get content and return code
        list($content, $httpcode, $time) = $this->_getContent($url);

        // print result for current page
        $this->_printResult($url, $depth, $httpcode, $time);

        // process subPages
        $this->_processAnchors($content, $url, $depth);
    }

    public function getHeaders($url)
    {
        $headers = get_headers($url, 1);

        // print_r($headers);
        echo "<pre>";
        echo "Crawler started for <b>".$this->_url."...</b>";
        echo "<br/>";
        echo "<br/>";
        echo "<b>Server Headers:</b>";
        echo "<br/>";

        foreach ($headers as $header=>$val)
        {
            echo "<b>".$header." :</b> ".$val."<br/>";
        }

        echo "<br/>";
        echo "<b>URL Content Anchors:</b>";
        echo "<br/>";
    }

    // post process function, saveing and displaying a sitemap
    public function post_process($url)
    {
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($this->_xml->asXML());
        $dom->saveXML();

        echo "<br/>";
        echo "<b>Crawler done.</b>";
        echo "<br/>";
        echo "Crawled <b>".count($this->_seen)."</b> entities in <b>".$this->_crawlTime."</b> seconds.";
        echo "<br/>";
        echo 'Crawler saved <b>'.$dom->save("sitemap.xml").'</b> bytes.';
        echo "<br/>";
        echo "</pre>";
        echo "</pre>";

        echo "<textarea wrap='off' style='width:100%; height: 100%;'>";
        echo $dom->saveXML();
        echo "</textarea>";
    }

    // add the seen filter
    public function addFilterPath($path)
    {
        $this->_filter[] = $path;
    }

    public function run()
    {
        $this->getHeaders($this->_url); // get the headers
        $this->crawl_page($this->_url, $this->_depth); // crawl the page
        $this->post_process($this->_url); // generate a XML sitemap
    }
}

// give the URL here
$startURL = 'http://public.redactor.co.za/';
$depth = 6;
$crawler = new crawler($startURL, $depth);
$crawler->run();

?>
