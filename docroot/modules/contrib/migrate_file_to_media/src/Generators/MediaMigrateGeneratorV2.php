<?php

namespace Drupal\migrate_file_to_media\Generators;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use DrupalCodeGenerator\Command\ModuleGenerator;
use DrupalCodeGenerator\ValidatorTrait;

/**
 * Automatically generates yml files for migrations.
 */
class MediaMigrateGeneratorV2 extends ModuleGenerator {

  /**
   * Command's name.
   *
   * @var string
   */
  protected string $name = 'migrate_file_to_media:media_migration_generator:v2';

  /**
   * Command's alias.
   *
   * @var string
   */
  protected string $alias = 'mf2m_media_v2';

  /**
   * Command's description.
   *
   * @var string
   */

  protected string $description = 'Generates yml for File to Media Migration';

  /**
   * Location of the templates.
   *
   * @var string
   */
  protected string $templatePath = __DIR__;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars): void {
    $this->collectDefault($vars);

    $vars['plugin_id'] = $this->ask('Plugin ID', 'Example', '::validatePluginId');
    $vars['migration_group'] = $this->ask('Migration Group', 'media');
    $vars['entity_type'] = $this->ask('Entity Type', 'node');
    $vars['source_bundle'] = $this->ask('Source Bundle', '');
    $vars['source_field_name'] = $this->ask('Source Field Names (comma separated)', 'field_image');
    $vars['target_bundle'] = $this->ask('Target Media Type', 'image');
    $vars['target_field'] = $this->ask('Target Media Type Field', 'field_media_image');
    $vars['source_lang_code'] = $this->ask('Language Code', 'en');
    $vars['translation_languages'] = $this->ask('Translation languages (comma separated)', 'none');
    $vars['include_revisions'] = $this->ask('Include field migration for all content revisions', 'no', '::validateRevisions');
    $vars['destination'] = $this->ask('Use migration file as a migration "plugin" or as "config"', 'plugin', '::validateDestination');

    $vars['translation_language'] = NULL;

    if ($vars['translation_languages']) {
      $translation_languages = array_map('trim', array_unique(explode(',', strtolower($vars['translation_languages']))));
      // Validate the default language was not included
      // in the translation languages.
      foreach ($translation_languages as $key => $language) {
        if ($language == $vars['source_lang_code']) {
          unset($translation_languages[$key]);
        }
      }
      $vars['translation_languages'] = $translation_languages;
    }

    // If we need migrate translation languages then the first default migration
    // step 1 must migrate all values without filtering by langcode to handle
    // the case when default langcode of the entity could be different from
    // the site default langcode.
    // Otherwise we will use the langcode filter for the default step 1.
    $vars['lang_code'] = $vars['translation_languages'] ? 'null' : $vars['source_lang_code'];

    if ($vars['source_field_name']) {
      $vars['source_field_name'] = array_map('trim', explode(',', strtolower($vars['source_field_name'])));
    }

    // ID Key for the entity type (nid for node, id for paragraphs).
    $entityType = $this->entityTypeManager->getDefinition($vars['entity_type']);
    $vars['id_key'] = $entityType->getKey('id');
    $vars['vid_key'] = $entityType->getKey('revision');

    $this->addFile('{destination}/migrate_plus.migration.{plugin_id}_step1.yml')
      ->template('media-migration-step1.yml.twig')
      ->vars($vars);

    // Validates if there are translation languages and includes a new variable
    // to add translations or not.
    $vars['has_translation'] = (count($vars['translation_languages']) > 0 && $vars['translation_languages'][0] != 'none');
    $this->addFile('{destination}/migrate_plus.migration.{plugin_id}_step2.yml')
      ->template('media-migration-step2.yml.twig')
      ->vars($vars);

    foreach ($vars['translation_languages'] as $language) {
      if ($language == 'none' || $language == $vars['lang_code']) {
        continue;
      }
      $vars['translation_language'] = $vars['lang_code'] = $language;

      $this->addFile("{destination}/migrate_plus.migration.{plugin_id}_step1_{$language}.yml")
        ->template('media-migration-step1.yml.twig')
        ->vars($vars);
    }
  }

  /**
   * Plugin id validator.
   */
  public static function validatePluginId($value): string {
    // Check the length of the global table name prefix.
    $connection_info = Database::getConnectionInfo();
    $db_info = array_shift($connection_info);
    $db_info = Database::parseConnectionInfo($db_info);

    $prefix = strlen($db_info['prefix']);
    if (!empty($db_info['prefix']['default'])) {
      $prefix = strlen($db_info['prefix']['default']);
    }
    $max_length = 42 - $prefix;

    // Check if the plugin machine name is valid.
    ValidatorTrait::validateMachineName($value);

    // Check the maximum number of characters for the migration name. The name
    // should not exceed 42 characters to prevent mysql table name limitation of
    // 64 characters for the table: migrate_message_[PLUGIN_ID]_step1
    // or migrate_message_[PLUGIN_ID]_step2.
    if (strlen($value) > $max_length) {
      throw new \UnexpectedValueException('The plugin id should not exceed more than ' . strval($max_length) . ' characters.');
    }
    return $value;
  }

  /**
   * Plugin 'include revisions' validator.
   */
  public static function validateRevisions($value): string {
    if (!in_array($value, ['no', 'n', 'yes', 'y'])) {
      throw new \UnexpectedValueException('Only two options are available: "yes/y" and "no/n".');
    }
    return $value === 'yes' || $value === 'y';
  }

  /**
   * Plugin destination validator.
   */
  public static function validateDestination($value): string {
    if (!in_array($value, ['plugin', 'config'])) {
      throw new \UnexpectedValueException('Only two options are available: "plugin" and "configs".');
    }
    return $value === 'plugin' ? 'migrations' : 'config/install';
  }

}
