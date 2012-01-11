<?php
/**
 * A Simple, simple proxy to install on a server with PHP, which has access to The Pirate Bay.
 *   For if you no longer have that access.
 **/
class AhoyProxy {
  public  $parsed = "";
  private $body   = "";
  private $path   = "";
  private $get    = array();
  private $url    = "";
  private $harbor = "http://ahoy.audrey";
  private $yonder = "thepiratebay.org";
  private $yonder_protocol = "https"; // @TODO: implement https version.

  function __construct() {
    $this->get = $_GET;

    if (isset($_GET['q'])) {
      $this->path = $this->search_to_path();
    }
    elseif (isset($_GET['path'])) {
      $this->path = $_GET['path'];
    }
    else {
      $this->path = "";
    }

    $this->url = "{$this->yonder_protocol}://{$this->yonder}/{$this->path}";
  }

  public function get() {
    $this->body = "";
    // @TODO: Implement using a custom socket. See http://nl2.php.net/stream_socket_client
    $ch = curl_init($this->url);
    //return the transfer as a string
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_HEADER, 0);
    curl_setopt($cu,CURLOPT_BINARYTRANSFER, true);
    curl_setopt($cu,CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
    curl_setopt($cu,CURLOPT_WRITEFUNCTION, 'read_body');
    curl_setopt($cu,CURLOPT_HEADERFUNCTION, 'read_header');
    $headers = apache_request_headers();
    $client_headers = array();
    foreach ($headers as $header => $value) {
      $client_headers[] = sprintf('%s: %s', $header, $value);
    }
    $client_headers[] = sprintf('X-Forwarded-For: %s', $_SERVER['REMOTE_ADDR']);
    curl_setopt($cu,CURLOPT_HTTPHEADER, $client_headers);

    try {
      $this->body = curl_exec($ch);
      var_dump(curl_getinfo($ch));
    }
    catch(Exception $e) {
      //Always close the socket thing.
      curl_close($ch);
      $this->body = "Caught exception: {$e->getMessage()}";
    }
    curl_close($ch);
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


  /**
   * Headers for socket
   */
  private function headers() {
    return 
      "GET {$this->path} HTTP/1.0\r\n".
      "Host: {$this->yonder_protocol}://{$this->yonder}\r\n".
      "Accept: */*\r\n\r\n";
  }
}


// @TODO clean this up and make this into a Router and Caller Class.
$ahoy = new AhoyProxy();
print $ahoy->get()->parse()->parsed;
?>
