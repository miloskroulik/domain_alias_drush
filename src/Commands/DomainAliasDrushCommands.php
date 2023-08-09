<?php

namespace Drupal\domain_alias_drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\domain\Commands\DomainCommandException;
use Drupal\domain_alias\DomainAliasInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the domain_alias_drush module
 */
class DomainAliasDrushCommands extends DrushCommands {

  /**
   * The domain alias entity storage service.
   *
   * @var \Drupal\domain_alias\DomainAliasStorageInterface
   */
  protected $domainAliasStorage = NULL;

  /**
   * Gets a domain alias storage object or throw an exception.
   *
   * Note that domain can run very early in the bootstrap, so we cannot
   * reliably inject this service.
   *
   * @return \Drupal\domain_alias\DomainAliasStorageInterface
   *   The domain alias storage handler.
   *
   * @throws \Drupal\domain\Commands\DomainCommandException
   */
  protected function getDomainAliasStorage() {
    if (!is_null($this->domainAliasStorage)) {
      return $this->domainAliasStorage;
    }

    try {
      $this->domainAliasStorage = \Drupal::entityTypeManager()->getStorage('domain_alias');
    }
    catch (PluginNotFoundException $e) {
      throw new DomainCommandException('Unable to get domain alias: no storage', $e);
    }
    catch (InvalidPluginDefinitionException $e) {
      throw new DomainCommandException('Unable to get domain alias: bad storage', $e);
    }

    return $this->domainAliasStorage;
  }

  /**
   * Adds a new domain alias
   *
   * @param $domain_id
   *   ID of the domain to add the alias to
   * @param $pattern
   *   Hostname pattern of the alias
   * @param $environment
   *   Environment the alias is created in (can be 'default', 'local', 'development', 'staging', 'testing')
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   * @option inactive
   *   Set the domain to inactive status if set.
   * @option redirect
   *   Set redirect for the alias. Can be Null, 301 or 302 (HTTP status)
   * @usage domain_alias_drush-commandName foo
   *   Usage description
   *
   * @command domain_alias_drush:add
   * @aliases daa
   */
  public function add($domain_id, $pattern, $environment = 'default', $options = ['redirect' => NULL]) {
    $domain_aliases = $this->getDomainAliasStorage()->loadMultiple();
    $values = [
      'status' => empty($options['inactive']),
      'id' => $this->createMachineName($pattern),
      'domain_id' => $domain_id,
      'pattern' => $pattern,
      'environment' => $environment,
      'redirect' => $options['redirect']
    ];
    /** @var DomainAliasInterface $domain_alias */
    $domain_alias = $this->getDomainAliasStorage()->create($values);

    // Check for pattern validity. This is required.
    // This had to be disabled, as design of DomainAliasValidator::validate requires existing DomainAlias entity
//    $valid = $this->validateDomainAlias($domain_alias);
//    if (!empty($valid)) {
//      \Drush\Drush::output()->writeln("AAA:" . $pattern);
//      throw new DomainCommandException(
//        dt('Pattern is not valid. !error',
//          ['!error' => $valid])
//      );
//    }
    // Check for hostname and id uniqueness.
    foreach ($domain_aliases as $existing) {
      if ($pattern === $existing->getPattern()) {
        throw new DomainCommandException(
          dt('No domain alias created. Pattern is a duplicate of !pattern.',
            ['!pattern' => $pattern])
        );
      }
      if ($values['id'] === $existing->id()) {
        throw new DomainCommandException(
          dt('No domain alias created. Id is a duplicate of !id.',
            ['!id' => $existing->id()])
        );
      }
    }

    try {
      $domain_alias->save();
    }
    catch (EntityStorageException $e) {
      throw new DomainCommandException('Unable to save domain alias', $e);
    }

    //$this->logger()->info(dt('Created @name at @domain.',
    //  ['@name' => $domain->label(), '@domain' => $domain_alias->getDomainId()]));
    $this->logger()->info('New alias has been created');
  }

  /**
   * Validates a domain alias confirms rules.
   *
   * @param \Drupal\domain\DomainAliasInterface $domain_alias
   *   The domain alias to validate for syntax and uniqueness.
   *
   * @return string|NULL
   *   Error if any has been found
   */
  protected function validateDomainAlias(DomainAliasInterface $domain_alias) {
    /** @var \Drupal\domain\DomainAliasValidatorInterface $validator */
    $validator = \Drupal::service('domain_alias.validator');
    return $validator->validate($domain_alias);
  }


  /**
   * Create a machine name from hostname pattern. Should be part of DomainAliasStorage.php
   *
   * @param $pattern
   * @return array|string|string[]|null
   */
  public function createMachineName($pattern) {
    return preg_replace('/[^a-z0-9_]/', '_', $pattern);

  }
}
