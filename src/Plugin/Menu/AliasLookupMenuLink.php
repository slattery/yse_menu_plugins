<?php

namespace Drupal\yse_menu_plugins\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route as SymfonyRoute;

# route_name - used for internal links. The route name that the link points to. If this is not set, the url key must be set.

class AliasLookupMenuLink extends MenuLinkDefault {
  protected $configuration;
  protected $pluginId;
  protected $pluginDefinition;
  protected $nid;
  protected $staticOverride;
  protected $options;
  protected $aliasManager;


  public function __construct(array $configuration, $plugin_id, $plugin_definition, $nid, StaticMenuLinkOverridesInterface $static_override, AliasManagerInterface $alias_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->nid = $nid;
    $this->staticOverride = $static_override;
    $this->aliasManager = $alias_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, $nid) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $nid,
      $container->get('menu_link.static.overrides'),
      $container->get('path_alias.manager'),
      $nid
    );
  }

  public function getTitle() {
    return $this->t('My Orders');
  }

  public function getUrlObject($title_attribute = TRUE) {
    \Drupal::messenger->addMessage(print_r([$this->configuration, $this->pluginDefinition]));
    $mnid = $this->nid;
    $options = [];
    return new Url('entity.node.canonical', ['node' => $mnid], $options);
  }

  protected function setParameterByUri($param, $idx, $options = []) {
    $segments = array_filter(explode('/', \Drupal::request()->getRequestUri()));
    if (!empty($segments) && $segments[$idx]) {
      #'2023', fit that into _path via Route and getNidFromAlias()
      $options['parameters'][$param] = $segments[$idx];
      return $options;
    }
  }

  protected function assembleAliasPath() {
    #use the Route object to properly swap the _path placeholders
    $rpath = $this - configuration['parameters']['_path'];
    $route = new Route(_path, $this->options);
    $rparams = $this->options['parameters'];
    $name = 'nope';
    $path = $this->getInternalPathFromRoute($name, $route, $rparams);
    return $path;
  }

  protected function assembleAliasByFieldValue() {
    // no-op rn
  }
  protected function getNidFromAlias($path) {
    $entity_path = $this->aliasManager->getPathByAlias($path);
    \Drupal::service('path_alias.manager')->getPathByAlias('/canopy/issues');
  }

  protected function getInternalPathFromRoute(string $name, SymfonyRoute $route, array $parameters = [], array &$query_params = []) {
    $compiledRoute = $route->compile();
    return $this->generateInternalPath($compiledRoute->getVariables(), $compiledRoute->getTokens(), $parameters);
  }

  protected function generateInternalPath(array $variables, array $tokens, array $parameters) {
    $variables = array_flip($variables);
    $url = '';
    foreach ($tokens as $token) {
      if ('variable' === $token[0]) {
        $url = $token[1] . $variables[$token[3]] . $url;
      }
      else {
        // Static text
        $url = $token[1] . $url;
      }
    }
    return $url;
  }
}
