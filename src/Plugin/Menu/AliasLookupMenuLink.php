<?php

namespace Drupal\yse_menu_plugins\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

# route_name - used for internal links. The route name that the link points to. If this is not set, the url key must be set.

class AliasLookupMenuLink extends MenuLinkDefault {
  protected $configuration;
  protected $pluginId;
  protected $pluginDefinition;
  protected $staticOverride;
  protected $alias_options;
  protected $aliasManager;


  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, AliasManagerInterface $alias_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override, $alias_manager);
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->staticOverride = $static_override;
    $this->aliasManager = $alias_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('path_alias.manager')
    );
  }

  public function getTitle() {
    $aliasconfig = $this->pluginDefinition['route_parameters'];
    if (!empty($aliasconfig) && array_key_exists('_name', $aliasconfig)) {
      return $this->t($aliasconfig['_name']);
    }
    else {
      return $this->t('Aliased Link');
    }
  }

  public function getUrlObject($title_attribute = TRUE) {
    $aliasconfig = $this->pluginDefinition['route_parameters'];
    $aliasoptarr = $this->pluginDefinition['options'];
    //process the 'parameters' from the '_options' array
    //these would become their own plugins w manager in future
    //gives us something like ['parameters' => ['pub_year' => '2023']]
    $aliasparams =
      array_key_exists('_options', $aliasconfig)
      ? array_map([$this, 'dispatchParamGetters'], $aliasconfig['_options'])
      : [];
    //make a false route to do path swaps on
    $aliaspath = $this->assembleAliasPath($aliasconfig['_path'], $aliasparams);
    //find a node with the assembled/populated path
    $aliasnode = $this->aliasManager->getPathByAlias($aliaspath);
    //getPathByAlias fails by returning the input so lets test
    list($emptyness, $nodestr, $urlgennid) = explode('/', $aliasnode);
    if (empty($urlgennid) || !is_numeric($urlgennid)) {
      $gennedurl = Url::fromUri('base:' . $aliasnode);
    }
    else {
      $gennedurl = new Url('entity.node.canonical', ['node' => $urlgennid], $aliasoptarr);
    }
    return $gennedurl;
  }

  public function getCacheMaxAge() {
    return 0;
  }

  public function getCacheContexts() {
    return [];
  }

  protected function dispatchParamGetters($a) {
    // assuming single arg!
    $reformed = [];
    foreach ($a as $param => $funcpair) {
      //assuming param_as_key => [action_as_key => value]
      // now
      $funct = key($funcpair);
      $value = $funcpair[$funct];
      switch ($funct) {
        case 'uri_segment':
          $parampair = $this->setParametersByUri($param, $value);
          $reformed[$param] = $parampair[$param];
        case 'field_value':
          //\Drupal::messenger()->addMessage($this->t('no func yet for param %p', ['%p' => $funct]));
          break;
        default:
          //report no match
          //\Drupal::messenger()->addMessage($this->t('no_match for param %p', ['%p' => $funct]));
          return FALSE;
      }
    }
    return $reformed;
  }
  protected function setParametersByUri($param, $idx, $pairs = []) {
    $segments = array_filter(explode('/', \Drupal::request()->getRequestUri()));
    if (!empty($segments) && $segments[$idx]) {
      #'2023', fit that into _path via Route and getNidFromAlias()
      $pairs[$param] = $segments[$idx];
      return $pairs;
    }
    else {
      return [$param => NULL];
    }
  }

  protected function assembleAliasPath($pathtpl, $pathparams) {
    #use the Route object to properly swap the _path placeholders
    $routeparams = !empty($pathparams) && array_key_exists('parameters', $pathparams)
      ? $pathparams['parameters'] : [];
    $route = new Route($pathtpl);
    $route->setDefaults($routeparams);
    $route->setoptions($pathparams);
    $name = 'nope';
    $path = $this->getInternalPathFromRoute($name, $route, $routeparams);
    return $path;
  }

  protected function assembleAliasByFieldValue() {
    // no-op rn
  }
  protected function getInternalPathFromRoute(string $name, Route $route, array $parameters = [], array &$query_params = []) {
    $compiledRoute = $route->compile();
    return $this->generateInternalPath($compiledRoute->getVariables(), $compiledRoute->getTokens(), $parameters);
  }

  protected function generateInternalPath(array $variables, array $tokens, array $parameters) {
    $variables = array_flip($variables);
    $url = '';
    //using params to fill, not grokking variables enough yet to get a roundtrip.
    $swaptokens = $tokens;
    foreach ($swaptokens as $token) {
      if ('variable' === $token[0]) {
        $url = $token[1] . $parameters[$token[3]] . $url;
      }
      else {
        // Static text
        $url = $token[1] . $url;
      }
    }
    return $url;
  }
}
