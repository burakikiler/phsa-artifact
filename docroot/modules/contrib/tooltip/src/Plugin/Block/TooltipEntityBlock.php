<?php

namespace Drupal\tooltip\Plugin\Block;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tooltip\TooltipBlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block to display an entity in a Tooltip.
 *
 * @Block(
 *   id = "tooltip_entity_block",
 *   admin_label = @Translation("Tooltip from entity"),
 *   category = @Translation("Tooltip")
 * )
 */
class TooltipEntityBlock extends TooltipBlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'entity_type' => null,
      'entity_id' => null,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $parents = $form_state->getTriggeringElement()['#parents'] ?? [];
    $trigger = end($parents);

    $input = $form_state->getUserInput()['settings'] ?? [];

    $entity = NULL;
    $entity_id = $input['entity_id'] ?? $this->configuration['entity_id'] ?? NULL;
    $entity_type = $input['entity_type'] ?? $this->configuration['entity_type'] ?? NULL;
    if ($entity_type && $entity_id) {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    }

    $wrapper_id = 'select-entity';

    $entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $type_id => $type) {
      if ($type instanceof ContentEntityTypeInterface) {
        $entity_types[$type_id] = $type->getSingularLabel();
      }
    }

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $entity_types,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'ajaxRefresh'],
        'wrapper' => $wrapper_id,
      ],
      '#default_value' => $entity_type,
    ];

    $form['entity_id'] = [
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];
    if ($entity_type) {
      $form['entity_id'] += [
        '#title' => ucfirst($entity_types[$entity_type]),
        '#type' => 'entity_autocomplete',
        '#target_type' => $entity_type,
        '#default_value' => $entity,
        '#selection_handler' => 'default',
      ];

      // Reset input.
      if ($trigger == 'entity_type') {
        $form['entity_id']['#value'] = NULL;
      }
    }

    return $form;
  }

  /**
   * Ajax refresh callback.
   */
  public function ajaxRefresh($form, FormStateInterface $form_state) {
    return $form['settings']['entity_id'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['entity_type'] = $form_state->getValue('entity_type');
    $this->configuration['entity_id'] = $form_state->getValue('entity_id');
  }

  /**
   * {@inheritdoc}
   */
  public function tooltip(array &$build) {
    $entity = NULL;
    $entity_id = $this->configuration['entity_id'] ?? NULL;
    $entity_type = $this->configuration['entity_type'] ?? NULL;
    if ($entity_id && $entity_type) {
      $entity = $this->entityTypeManager->getStorage($entity_type)
        ->load($entity_id);
    }

    if (!$entity) {
      return $build;
    }

    $build['entity'] = $this->entityTypeManager->getViewBuilder($entity_type)
      ->view($entity, 'tooltip');
    $build['#access'] = $entity->access('view');
  }

}
