<?php

namespace Drupal\group_by_field_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity_reference_group_by_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_group_by_field_widget",
 *   module = "group_by_field_widget",
 *   label = @Translation("Entity reference group by field widget"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class EntityReferenceGroupByFieldWidget extends OptionsWidgetBase
{

  /**
   * Drupal\Core\Entity\EntityFieldManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new OptionsShsWidget object.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity Type Manager for loading field details.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager)
  {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->groupableFields = ['boolean', "entity_reference"];
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
      'group_by' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $elements = [];

    $elements['group_by'] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Select element'),
      '#options' => $this->getGroupOptions(),
      '#default_value' => $this->getSetting('group_by'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = [];

    $summary[] = t('Group By: @list', ['@list' => $this->getSetting('group_by')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $settings = $this->fieldDefinition->getSettings();

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $options = $this->getOptions($items->getEntity());
    $selected = $this->getSelectedOptions($items);

    $element += array(
      '#type' => 'details',
    );

    $option_entitities = $this->entityTypeManager->getStorage($settings['target_type'])->loadMultiple(array_keys($options));


    foreach ($options as $options_key => $optionLabel) {

      $this->groupFormElements($element, $option_entitities[$options_key], $selected, $options_key, $optionLabel);

    }

    return $element;
  }


  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Initiate response.
    $massaged_values = [];
    // Get user values
    $input_values = $form_state->getUserInput();

    if ($this->multiple) {
      // Get the ids of each of the selected options and build
      foreach (array_keys(array_filter($input_values[$this->fieldDefinition->getName()])) as $value) {
        $massaged_values[] = ['target_id' => $value];
      }
    } else {
      $massaged_values[] = ['target_id' => $input_values[$this->fieldDefinition->getName()]];
    }
    return $massaged_values;
  }

  protected function getGroupOptions()
  {
    $settings = $this->fieldDefinition->getSettings();
    $list = [];

    if (isset($settings['handler_settings']['target_bundles'])) {
      foreach ($settings['handler_settings']['target_bundles'] as $target_bundle) {
        $list = $list + $this->getFieldTree($settings['target_type'], $target_bundle);
      }
    }

    return $list;
  }

  protected function getFieldTree($entity_type, $bundle, &$field_list = [], $field_path = [])
  {
    $list = [];

    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type, $bundle) as $key => $field_definitions) {
      $field_definition_name = $entity_type . "." . $key;
      // If new field determine if groupable.
      if (!in_array($field_definition_name, array_keys($field_list))) {
        $field_list[$field_definition_name] = $this->isGroupable($field_definitions);
      }

      // If groupable add to list.
      if ($field_list[$field_definition_name]) {

        $field_def_path = [];
        // Get key and value names.
        if (empty($field_path)) {
          $field_def_path['field_key'] = $key;
          $field_def_path['field_label'] = $field_definitions->getLabel();
        } else {
          $field_def_path['field_key']  = $field_path['field_key'] . "." . $key;
          $field_def_path['field_label'] = $field_path['field_label'] . " => " . $field_definitions->getLabel();
        }


        $list[$field_def_path['field_key']] = $field_def_path['field_label'];

        // Add nested fields
        if ($field_definitions->getType() == 'entity_reference') {
          $sub_field_settings = $field_definitions->getSettings();


          if (isset($sub_field_settings['handler_settings']['target_bundles'])) {
            foreach ($sub_field_settings['handler_settings']['target_bundles'] as $sub_bundle) {
              $list = $list + $this->getFieldTree($sub_field_settings['target_type'], $sub_bundle, $field_list, $field_def_path);
            }
          }
        }
      }
    }
    return $list;
  }

  protected function isGroupable(FieldDefinitionInterface $field)
  {
    // Only fields with a single option is allowed
    if ($field->getFieldStorageDefinition()->getCardinality() !== 1) {
      return false;
    } else if (!in_array($field->getType(), $this->groupableFields)) {
      return false;
    }

    return true;
  }

  protected function groupFormElements(&$element, EntityInterface $entity, $selected, $options_key, $optionLabel, $depth = 0)
  {
    $group_by_list = [$this->getSetting('group_by')];

    if( count($group_by_list) != $depth) {
      // Put in array for eventual nested grouping
      $group_details = $this->parseGroupDetails(explode('.', $group_by_list[$depth]), $entity);
      $group_label = 'group_'.$group_details['key'];

      if (!isset($element[$group_label])) {
        $element[$group_label] = array(
          '#type' => 'details',
          '#title' => $group_details['label'],
        );
      }
      \Drupal::logger('scad_signage')->info($depth);

      $this->groupFormElements($element[$group_label], $entity, $selected, $options_key, $optionLabel, ++$depth);
    } else {
      // Common settings between checkbox and radio buttons;
      $element[$options_key] = array(
        '#title' => $optionLabel,
        '#default_value' => in_array($options_key, $selected) ? 1 : 0,
      );

      if ($this->multiple) {
        $element[$options_key] += ['#type' => 'checkbox'];
      } else {
        $element[$options_key] += array(
          '#type' => 'radio',
          '#parents' => array($this->fieldDefinition->getName() ),
          '#return_value' => $options_key,
          '#attributes' => in_array($options_key, $selected) ? ['checked'=>"checked"] : [],
        );
      }
    }
  }

  protected function parseGroupDetails($field_chain, EntityInterface $entity, $depth = 0)
  {
    $details = [
      'key' => 'na',
      'label' => 'No Value',
    ];

    if (count($field_chain) != $depth) {
      $field_list = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
      $value = $entity->get($field_chain[$depth])->getValue();
      $settings =  $field_list[$field_chain[$depth]]->getSettings();

      if (isset($value[0]['target_id'])) {
        $entity = $this->entityTypeManager->getStorage($settings['target_type'])->load($value[0]['target_id']);
        $details = $this->parseGroupDetails($field_chain, $entity ,++$depth);
      }
    } else {
      $details = [
        'key' => $entity->id(),
        'label' => $entity->label(),
      ];
    }

    return $details;
  }

}
