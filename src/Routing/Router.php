<?php
namespace Cubex\Routing;

use Cubex\CubexAwareTrait;

class Router implements IRouter
{
  use CubexAwareTrait;

  /**
   * @var IRoutable entity to pull route table from
   */
  protected $_subject;

  /**
   * @var array Data collected from the route
   */
  protected $_routeData;

  /**
   * @var string path segments which have previsouly been matched
   */
  protected $_fullUrl;

  /**
   * Set the object you wish to handle routing for
   *
   * @param IRoutable $subject
   *
   * @return $this
   */
  public function setSubject(IRoutable $subject)
  {
    $this->_subject = $subject;
    return $this;
  }

  /**
   * Process the url against the subjects routes
   *
   * @param string $url     url section to parse
   * @param string $fullUrl full request url
   *
   * @return IRoute
   * @throws \RuntimeException When the subject has not been set
   * @throws \Exception When no route can be found
   */
  public function process($url, $fullUrl = null)
  {
    if($this->_subject === null || !($this->_subject instanceof IRoutable))
    {
      throw new \RuntimeException("No routable subject has been defined");
    }

    $this->_fullUrl = ltrim(nonempty($fullUrl, $url), '/');
    $route = $this->_processRoutes($url, $this->_subject->getRoutes());
    if($route instanceof IRoute)
    {
      return $route;
    }

    throw new RouteNotFoundException("Unable to locate a suitable route");
  }

  protected function _processRoutes($url, $routes)
  {
    if(!is_array($routes))
    {
      return null;
    }

    $url = ltrim($url, '/');

    foreach($routes as $pattern => $route)
    {
      if(starts_with($pattern, '/'))
      {
        $search = $this->_fullUrl;
      }
      else
      {
        $search = $url;
      }
      $pattern = ltrim($pattern, '/');
      $matchedPath = $this->matchPattern($search, $pattern);
      if($matchedPath !== false)
      {
        $subUrl = preg_replace(
          '#^' . preg_quote($matchedPath, '#') . '#',
          '',
          $search
        );
        if(is_callable($route))
        {
          return $this->createRoute($route, $matchedPath);
        }
        if(is_array($route))
        {
          $subMatch = $this->_processRoutes($subUrl, $route);
          if($subMatch !== null)
          {
            return $this->createRoute(
              $subMatch->getValue(),
              build_path_unix($matchedPath, $subMatch->getMatchedPath())
            );
          }
          return null;
        }
        return $this->createRoute($route, $matchedPath);
      }
    }

    return null;
  }

  public function matchPattern($url, $pattern)
  {
    if($pattern == '' && $url == '')
    {
      return '';
    }

    //We need a pattern to match, null or empty are too vague
    if(empty($pattern))
    {
      return false;
    }

    $pattern = self::convertSimpleRoute($pattern);
    $match = preg_match("#^$pattern(/|$)#U", $url, $matches);

    if($match)
    {
      foreach($matches as $k => $v)
      {
        //Strip out all non declared matches
        if(!\is_numeric($k))
        {
          $this->_routeData[$k] = $v;
        }
      }
      return $matches[0];
    }
    return false;
  }

  /**
   * Create a route from the raw data
   *
   * @param $data
   * @param $matchedPath
   *
   * @return Route
   */
  public function createRoute($data, $matchedPath = '')
  {
    if($data instanceof IRoute)
    {
      return $data;
    }

    return Route::create($data, $this->_routeData, $matchedPath);
  }

  /**
   * Convert simple routes e.g. :name@num to full regex strings
   *
   * @param $route
   *
   * @return mixed
   */
  public static function convertSimpleRoute($route)
  {
    $idPat = "(_?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)";
    $repl = [];
    /* Allow Simple Routes */

    if(strstr($route, '{'))
    {
      $repl["/{" . "$idPat\@alphanum}/"] = "(?P<$1>\w+)";
      $repl["/{" . "$idPat\@alnum}/"] = "(?P<$1>\w+)";
      $repl["/{" . "$idPat\@alpha}/"] = "(?P<$1>[a-zA-Z]+)";
      $repl["/{" . "$idPat\@all}/"] = "(?P<$1>.+?)";
      $repl["/{" . "$idPat\@num}/"] = "(?P<$1>\d+)";
      $repl["/{" . "$idPat}/"] = "(?P<$1>.+)";
    }

    if(strstr($route, ':'))
    {
      $repl["/\:$idPat\@alphanum/"] = "(?P<$1>\w+)";
      $repl["/\:$idPat\@alnum/"] = "(?P<$1>\w+)";
      $repl["/\:$idPat\@alpha/"] = "(?P<$1>[a-zA-Z]+)";
      $repl["/\:$idPat\@all/"] = "(?P<$1>.+?)";
      $repl["/\:$idPat\@num/"] = "(?P<$1>\d+)";
      $repl["/\:$idPat/"] = "(?P<$1>.+)";
    }

    if(!empty($repl))
    {
      $route = preg_replace(array_keys($repl), array_values($repl), $route);
    }

    return str_replace('//', '/', $route);
  }
}
