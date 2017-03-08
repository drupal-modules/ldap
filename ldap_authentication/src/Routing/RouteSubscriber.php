<?php

namespace Drupal\ldap_authentication\Routing;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\ldap_authentication\Helper\LdapAuthenticationConfiguration;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * @package Drupal\ldap_authentication\Routing
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {
  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('user.pass')) {
      $route->setRequirement('_custom_access', '\Drupal\ldap_authentication\Routing\RouteSubscriber::validateResetPasswordAllowed');
    }
  }

  public static function validateResetPasswordAllowed() {
    $user = \Drupal::currentUser();
    if ($user->isAnonymous()) {

      if (\Drupal::config('ldap_authentication.settings')->get('ldap_authentication_conf.authenticationMode') == LdapAuthenticationConfiguration::$mode_mixed) {
        return AccessResult::allowed();
      }

      /**
       * Hide reset password for anonymous users if LDAP-only authentication and
       * password updates are disabled, otherwise show.
       */
      if (\Drupal::config('ldap_authentication.settings')->get('ldap_authentication_conf.passwordOption') == LdapAuthenticationConfiguration::$passwordFieldAllow) {
        return AccessResult::allowed();
      } else {
        return AccessResult::forbidden();
      }
    } else {
      return AccessResult::forbidden();
    }
  }
}