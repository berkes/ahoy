<pre>
<?php
error_reporting(E_ALL);
ini_set('display_errors', "1");
/**
 * A Simple, simple proxy to install on a server with PHP, which has access to The Pirate Bay.
 *   For if you no longer have that access.
 **/
class AhoyProxy {
  public  $parsed = "";
  private $body   = "";
  private $get    = array();
  private $harbor = "http://ahoy.webschuur.com";

  function __construct() {
    $this->get = $_GET;
    $path = "";

    if (isset($_GET['q'])) {
      $path = $this->search_to_path();
    }
    elseif (isset($_GET['path'])) {
      $path = $_GET['path'];
    }

    $this->yonder($path);
  }

  public function get() {
    $errno = 0;
    $errstr = "";
    $this->body = "";

    var_dump($this->socket_url());
    $fp = stream_socket_client("{$this->socket_url()}", $errno, $errstr);

    var_dump(array("errno" => $errno, "errstr" => $errstr));
    if (!$fp) {
      fclose($fp); //@TODO: does this make any sense?
      throw new Exception("Socket could not be opened. Socket: $socket. Error: $errstr ($errno)");
    }

    $headers = apache_request_headers();
    $headers += array(
      "User-agent" => $_SERVER['HTTP_USER_AGENT'], //@TODO if empty, fallback on custom UA.
      "X-Forwarded-For" => $_SERVER['REMOTE_ADDR'],
    );

    $request = "GET {$this->yonder()->path} HTTP/1.0\r\n";

    foreach ($headers as $name => $value) {
      $value = trim($value);
      $request .= "{$name}: {$value}";
    }

    var_dump($request);
    fwrite($fp, $request);
    while (!feof($fp)) {
      $this->body = fgets($fp, 1024);
    }
    var_dump($fp);
    fclose($fp);

    return $this;
  }

  public function parse() {
    if (!empty($this->body)) {
      $this->parsed = $this->replace($this->body);
    }
    else {
      $this->parsed = "HARRRR";
      $this->parsed .= "Got empty at {$this->url}";
    }
    return $this;
  }

  private function replace($text) {
    return preg_replace("@{$this->source_matcher()}@", $this->source_replacer(),$text);
  }

  private function search_to_path() {
    $q = urlencode($this->get['q']);
    $p = urlencode($this->get['page']);
    $o = urlencode($this->get['orderby']);
    $c = urlencode($this->get['category']);
    return "search/{$q}";
  }

  /**
   * PREGs that describe valid sources to route trough
   */
  private function source_matcher() {
    return '(href=|src=|action=)"(http(s)?://(.*\.)?thepiratebay.org)?(/([^"]*))?"';
  }
  private function source_replacer() {
    return '\1"'.$this->harbor.'?path=\6"';
  }

  private function yonder($path = "/") {
    static $uri;
    if (empty($uri)) {
      $uri = new URI();
      // @TODO detect if request is https, if so connect to remote over https too.
      $uri->scheme = "http";
      $uri->host   = "thepiratebay.se";
      $uri->path   = $path;
    }
    return $uri;
  }
  /**
   * String as url for socket
   */
  private function socket_url() {
    $uri = new URI();
    switch ($this->yonder()->scheme) {
      case 'http':
      case 'feed':
        $uri->scheme = "tcp";
        $uri->host   = $this->yonder()->host;
        $uri->port   = $this->yonder()->port;
        $uri->path   = $this->yonder()->path;
        break;
      case 'https':
        //@TODO implement https, with a ssl:// protocol and port 443.
      default:
        throw new Exception("Invalid protocol");
        break;
    }
    return "{$uri}";
  }
}

/**
 * Simple URI class; contains the relevant information that describes a URI.
 * @TODO implement the other retvals from http://nl2.php.net/parse_url
 **/
class URI {
  public $scheme = "http";
  public $host   = "";
  public $port   = 80;
  public $path   = "/";
  function __construct($uri = "") {
    if (!empty($uri)) {
      $null = null;
      list($this->scheme, $this->host, $this->port, $null, $null, $this->path) = parse_url($uri);
      unset($null); //Fuck you too, PHP.
    }
  }

  public function __toString() {
    return "{$this->scheme}://{$this->host}:{$this->port}";
  }

  public function toArray() {
    return array(
      "scheme" => $this->scheme,
      "host"   => $this->host,
      "port"   => $this->port,
      "path"   => $this->path,
    );
  }
}


// @TODO clean this up and make this into a Router and Caller Class.
$ahoy = new AhoyProxy();
print $ahoy->get()->parse()->parsed;
var_dump($ahoy);
?>
