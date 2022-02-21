<?php

namespace Drupal\group_by_field_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity_reference_group_by_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_group_by_field_widget",
 *   module = "group_by_field_widget",
 *   label = @Translation("Entity reference group by field widget"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceGroupByFieldWidget extends OptionsButtonsWidget
{

  /**
   * Drupal\Core\Entity\EntityFieldManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

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
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityFieldManagerInterface $entity_field_manager)
  {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityFieldManager = $entity_field_manager;
    $this->groupableFields = ['boolean',"entity_reference"];
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
      $container->get('entity_field.manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
      'size' => 60,
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $elements = [];


    $elements['size'] = [
      '#type' => 'number',
      '#title' => t('Size of textfield'),
      '#default_value' => $this->getSetting('size'),
      '#required' => TRUE,
      '#min' => 1,
    ];
    $elements['placeholder'] = [
      '#type' => 'textfield',
      '#title' => t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = [];

    $summary[] = t('Textfield size: @size', ['@size' => $this->getSetting('size')]);
    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_length'),
    ];

    return $element;
  }

  protected function getGroupOptions()
  {
    $settings = $this->fieldDefinition->getSettings();
    $list = [];

    foreach ($settings['handler_settings']['target_bundles'] as $key => $value) {
      $list = $list + $this->getFieldTree($settings['target_type'], $value);
    }

    return $list;
  }


  protected function getFieldTree($entity_type, $bundle, &$field_list = [], $field_path = [])
  {
    $list = [];

    foreach ( $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle) as $key => $value) {
      $field_definition_name = $entity_type.".".$key;
      // If new field determine if groupable.
      if (!in_array($field_definition_name, array_keys($field_list)) ) {
        $field_list[$field_definition_name] = $this->isGroupable($value);
      }

      // If groupable add to list.
      if ($field_list[$field_definition_name]) {
        $field_def_path = [];
        // Get key and value names.
        if (empty($field_path)) {
          $field_def_path['field_key'] = $key;
          $field_def_path['field_label'] = $value->getLabel();
        } else {
          $field_def_path['field_key']  = $field_path['field_key'] . "." . $key;
          $field_def_path['field_label'] = $field_path['field_label'] . " => " .$value->getLabel();
        }

        $list[$field_def_path['field_key']] = $field_def_path['field_label'];


        // Add nested fields
        // $list + $this->getFieldTree($field_type, $field_bundle, $field_list, $field_def_path);
      }
    }

    return $list;
  }

  protected function isGroupable(FieldDefinitionInterface $field)
  {

    if ($field->getFieldStorageDefinition()->getCardinality() !== 1) {
      return false;
    } else if ( !in_array($field->getType(), $this->groupableFields)  ) {
      return false;
    }

    return true;
  }
}
