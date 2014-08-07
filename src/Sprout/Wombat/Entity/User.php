<?php

/**
 * User
 *
 * Store access info retrieved from BigCommerce
 */

namespace Sprout\Wombat\Entity;

class User {

  /**
   * access token provided for the user
   */
  protected $access_token;

  /**
   * List of authorization scopes
   */
  protected $scope;

  /**
   * User information
   *
   * user.id  integer Unique identifier for the user
   * user.email  string  The user’s email address
   */
  protected $user;

  /**
   * Base path for the authorized store context, in the format: stores/{store_hash}
   */
  protected $context;

  public function __construct($attributes) {
    $this->setAttributes($attributes);
  }

  public function getAttributes() {
    return array(
      'access_token' => $this->access_token,
      'scope' => $this->scope,
      'user' => $this->user,
      'context' => $this->context,
      );
  }
  public function setAttributes($attributes) {
    if(is_array($attributes)) {
      $this->access_token = $attributes['access_token'];
      $this->scope        = $attributes['scope'];
      $this->user         = $attributes['user'];
      $this->context      = $attributes['context'];
    }
  }

  public function getAccessToken() {
    return $this->access_token;
  }

  public function setAccessToken($access_token) {
    $this->access_token = $access_token;
  }

  public function getScope() {
    return $this->scope;
  }

  public function setScope($scope) {
    $this->scope = $scope;
  }

  public function getUser() {
    return $this->user;
  }

  public function setUser($user) {
    $this->user = $user;
  }

  public function getContext() {
    return $this->context;
  }

  public function setContext($context) {
    $this->context = $context;
  }


}